<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation)
    {
        Gate::authorize('viewAny', [Message::class, $conversation]);

        $messages = $conversation->messages()
            ->with(['sender', 'attachments', 'reactions.user', 'replyTo'])
            ->whereDoesntHave('hiddenFor', fn ($q) => $q->where('users.id', $request->user()->id))
            ->latest()
            ->cursorPaginate(30);

        return response()->json($messages);
    }

  public function store(Request $request, Conversation $conversation)
  {
    Gate::authorize('create', [Message::class, $conversation]);

    $type = $request->input('type');

    $validated = $request->validate([
        'type' => ['required', 'in:text,image,file,voice'],

        'body' => [
            Rule::requiredIf($type === 'text'),
            Rule::prohibitedIf($type === 'voice'),
            'nullable',
            'string',
        ],

        'reply_to_id' => [
            'nullable',
            'uuid',
            Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
        ],

        'file' => [
            Rule::requiredIf($type !== 'text'),
            Rule::prohibitedIf($type === 'text'),
            'nullable',
            'file',
            'max:10240',
            Rule::when($type === 'image', ['image', 'mimes:jpg,jpeg,png,webp']),
            Rule::when($type === 'voice', ['mimes:mp3,wav,ogg,m4a']),
            Rule::when($type === 'file', ['mimes:pdf,doc,docx,xls,xlsx,zip,txt']),
        ],
    ]);

    $userId = $request->user()->id;
    $message = DB::transaction(function () use ($validated, $conversation, $userId) {
        $message = $conversation->messages()->create([
            'user_id' => $userId,
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        if (isset($validated['file'])) {
            $this->storeAttachment($message, $validated['file']);
        }

        return $message;
    });
    broadcast(new MessageSent($message))->toOthers();

    return response()->json($message->load(['sender', 'replyTo', 'attachments']), 201);
  }

   public function update(Request $request, Message $message)
    {
        Gate::authorize('update', $message);

        if ($message->type === 'voice') {
            return response()->json(['message' => 'Voice messages cannot be edited.'], 422);
        }

        $validated = $request->validate([
            'body' => [
                Rule::requiredIf($message->type === 'text'),
                'nullable',
                'string',
            ],
        ]);

        $message->update([
            'body' => $validated['body'] ?? null,
            'edited_at' => now(),
        ]);

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json($message);
    }

    public function destroy(Message $message)
    {
        Gate::authorize('delete', $message);

        $message->delete(); // soft delete — "delete for everyone"

        broadcast(new MessageDeleted($message->id, $message->conversation_id))->toOthers();

        return response()->noContent();
    }

    public function hide(Request $request, Message $message)
    {
        Gate::authorize('hide', $message);

        $message->hiddenFor()->syncWithoutDetaching($request->user()->id);

        return response()->noContent();
    }

    private function storeAttachment(Message $message, UploadedFile $file): void
    {
        $path = $file->store("conversations/{$message->conversation_id}", 'local');
        $thumbnailPath = null;

        if ($message->type === 'image') {
            $manager = ImageManager::usingDriver(Driver::class);
            $thumbnail = $manager->decodeSplFileInfo($file)->scaleDown(width: 400)->encodeUsingFormat(Format::JPEG, quality: 75);
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $thumbnailPath = dirname($path) . "/thumb_{$filename}.jpg";
            Storage::disk('local')->put($thumbnailPath, (string) $thumbnail);
        }

        $message->attachments()->create([
            'file_path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

}
