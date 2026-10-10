<?php

namespace App\Http\Controllers;

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
        $conversations = $request->user()
            ->conversations()
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->whereNull('participants.left_at')
            ->orderByDesc(
                DB::table('messages')
                    ->select('created_at')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        return response()->json($conversations);
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
                return response()->json($existing->load('participants.user'));
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

        return response()->json($conversation->load('participants.user'), 201);
    }

      public function show(Conversation $conversation)
    {
        Gate::authorize('view',$conversation);

        return response()->json(
            $conversation->load(['participants.user', 'messages' => fn ($q) => $q->latest()->limit(50)])
        );
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

    return response()->json($conversation);
}

     public function addParticipant(Request $request, Conversation $conversation)
    {
        Gate::authorize('addParticipant',$conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $conversation->participants()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            ['role' => 'member','left_at' => null]
        );

        return response()->json($conversation->load('participants.user'));
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

    return response()->json($conversation);
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

    return response()->json($conversations);
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
