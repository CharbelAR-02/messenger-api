<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $validated = $request->validate([
            'type' => ['required', 'in:text,image,file,voice'],
            'body' => ['required_if:type,text', 'nullable', 'string'],
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
        ]);

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'body' => $validated['body'] ?? null,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
        ]);

        return response()->json($message->load(['sender', 'replyTo']), 201);
    }

    public function update(Request $request, Message $message)
    {
        Gate::authorize('update', $message);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        return response()->json($message);
    }

    public function destroy(Message $message)
    {
        Gate::authorize('delete', $message);

        $message->delete(); // soft delete — "delete for everyone"

        return response()->noContent();
    }

    public function hide(Request $request, Message $message)
    {
        Gate::authorize('hide', $message);

        $message->hiddenFor()->syncWithoutDetaching($request->user()->id);

        return response()->noContent();
    }
}
