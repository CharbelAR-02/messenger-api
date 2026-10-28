<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class ConversationController extends Controller
{
    public function index(Request $request)
{
    $userId = $request->user()->id;

    $conversations = $request->user()
        ->conversations()
        ->with(['participants.user','messages' => fn ($q) => $q->orderByDesc('id')->limit(1)])
        ->withCount(['messages as unread_count' => function ($q) use ($userId) {
            $q->where('messages.user_id', '!=', $userId)
                ->where(function ($q) {
                    $q->whereNull('participants.last_read_message_id')
                        ->orWhereColumn('messages.id', '>', 'participants.last_read_message_id');
                })
                ->whereDoesntHave('hiddenFor', fn ($h) => $h->where('users.id', $userId));
        }])
        ->whereNull('participants.left_at')
        ->orderByDesc(
            DB::table('messages')
                ->select('id')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(1)
        )
        ->orderByDesc('conversations.id')
        ->get();

    return ConversationResource::collection($conversations);
}
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:private,group'],
            'name' => ['required_if:type,group', 'nullable', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['uuid', 'exists:users,id', Rule::notIn([$request->user()->id])],
        ]);

        $userId = $request->user()->id;

        if ($validated['type'] === 'private') {
            if (count($validated['participant_ids']) !== 1) {
                return response()->json(['message' => 'A private conversation needs exactly one other participant.'], 422);
            }

            $otherId = $validated['participant_ids'][0];

            // Don't create a duplicate — reuse an existing private conversation between these two users.
            $existing = Conversation::where('type', 'private')
                ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $otherId))
                ->first();

            if ($existing) {
               return new ConversationResource($existing->load('participants.user'));
            }
        }

        $conversation = DB::transaction(function () use ($validated, $userId) {
            $conversation = Conversation::create([
                'type' => $validated['type'],
                'name' => $validated['type'] === 'group' ? $validated['name'] : null,
                'created_by' => $userId,
            ]);

            $conversation->participants()->create([
                'user_id' => $userId,
                'role' => 'admin', // creator is always admin — matters for groups, harmless for private
            ]);

            foreach ($validated['participant_ids'] as $otherId) {
                $conversation->participants()->create([
                    'user_id' => $otherId,
                    'role' => 'member',
                ]);
            }

            return $conversation;
        });

       return new ConversationResource($conversation->load('participants.user'));
    }

      public function show(Conversation $conversation)
    {
        Gate::authorize('view',$conversation);

        return new ConversationResource($conversation->load('participants.user'));
    }

     public function update(Request $request, Conversation $conversation)
{
    Gate::authorize('update', $conversation);

    $validated = $request->validate([
        'name' => ['sometimes', 'string', 'max:255'],
        'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    $oldAvatarPath = $conversation->avatar_path;

    if ($request->hasFile('avatar')) {
        $conversation->avatar_path = $this->storeAvatar($request->file('avatar'));
    }

    if (array_key_exists('name', $validated)) {
        $conversation->name = $validated['name'];
    }

    $conversation->save();

    if ($request->hasFile('avatar') && $oldAvatarPath) {
        Storage::disk('public')->delete($oldAvatarPath);
    }

    return new ConversationResource($conversation);
}

     public function addParticipant(Request $request, Conversation $conversation)
    {
        Gate::authorize('addParticipant', $conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $participant = $conversation->participants()->firstOrNew(['user_id' => $validated['user_id']]);

        // Active members are left untouched: never overwrite a role or a read pointer.
        if (! $participant->exists || $participant->left_at !== null) {
            $participant->role = 'member';
            $participant->left_at = null;
            $participant->joined_at = now();
            // Start at the latest message, so history doesn't show up as unread.
            $participant->last_read_message_id = $conversation->messages()->orderByDesc('id')->value('id');
            $participant->save();
        }

        return new ConversationResource($conversation->load('participants.user'));
    }

    public function removeParticipant(Conversation $conversation, User $user)
    {
        Gate::authorize('removeParticipant',[$conversation,$user]);

        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['left_at' => now()]);

        return response()->noContent();
    }

    public function deleteAvatar(Conversation $conversation)
{
    Gate::authorize('update', $conversation);

    if ($conversation->avatar_path) {
        Storage::disk('public')->delete($conversation->avatar_path);
        $conversation->update(['avatar_path' => null]);
    }

    return new ConversationResource($conversation);
}

public function search(Request $request)
{
    $validated = $request->validate([
        'q' => ['required', 'string', 'min:1', 'max:255'],
    ]);

    $userId = $request->user()->id;
    $term = $validated['q'];

    $conversations = $request->user()->conversations()
        ->whereNull('participants.left_at')
        ->where(function ($query) use ($term, $userId) {
            $query->where('conversations.name', 'like', "%{$term}%")
                ->orWhereHas('participants', function ($q) use ($term, $userId) {
                    $q->where('user_id', '!=', $userId)
                        ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
                });
        })
        ->with(['participants.user'])
        ->get();

    return ConversationResource::collection($conversations);
}

private function storeAvatar($file): string
    {
        $manager = ImageManager::usingDriver(Driver::class);
        $resized = $manager->decodeSplFileInfo($file)
            ->scaleDown(width: 500, height: 500)
            ->encodeUsingFormat(Format::JPEG, quality: 80);

        $filename = 'avatars/' . uniqid() . '.jpg';
        Storage::disk('public')->put($filename, (string) $resized);

        return $filename;
    }

}
