<?php

use App\Events\MessageSent;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Reaction;
use Illuminate\Testing\TestResponse;

function expectNoLeakedFields(TestResponse $response, array $also = []): void
{
    $forbidden = array_merge([
        'email_verified_at', 'avatar_path', 'file_path', 'thumbnail_path',
        'pivot', 'deleted_at', 'updated_at', 'password', 'remember_token',
    ], $also);

    foreach ($forbidden as $field) {
        expect($response->getContent())->not->toContain('"'.$field.'"');
    }
}

// A conversation with a reply, an image attachment, a reaction, and avatars.
function richConversation(): array
{
    $admin = makeUser(['name' => 'Alice Admin', 'avatar_path' => 'avatars/alice.jpg']);
    $member = makeUser(['name' => 'Bob Member']);
    $conversation = makeConversation($admin, [$member]);
    $conversation->update(['avatar_path' => 'avatars/group.jpg']);

    $first = makeMessage($conversation, $admin, ['body' => 'first']);
    makeMessage($conversation, $member, ['body' => 'reply', 'reply_to_id' => $first->id]);
    $photo = makeMessage($conversation, $admin, ['type' => 'image', 'body' => null]);
    $photo->attachments()->create([
        'file_path' => 'conversations/x/photo.jpg',
        'thumbnail_path' => 'conversations/x/thumb_photo.jpg',
        'file_type' => 'image/jpeg',
        'file_size' => 10,
    ]);
    Reaction::create(['message_id' => $first->id, 'user_id' => $member->id, 'emoji' => '👍']);

    return [$admin, $member, $conversation, $photo];
}

it('never leaks private fields in the conversation list', function () {
    [, $member] = richConversation();

    $this->actingAs($member, 'web');

    $response = $this->getJson('/api/conversations')
        ->assertOk()
        ->assertJsonPath('0.membership.role', 'member')
        ->assertJsonPath('0.unread_count', 2);

    expectNoLeakedFields($response, ['email']);
    expect($response->json('0.avatar_url'))->toContain('avatars/group.jpg');
});


it('never leaks private fields when showing a conversation', function () {
    [, $member, $conversation] = richConversation();

    $this->actingAs($member, 'web');

    $response = $this->getJson("/api/conversations/{$conversation->id}")->assertOk();

    expectNoLeakedFields($response, ['email']);
});

it('returns messages with exactly the documented fields', function () {
    [, $member, $conversation] = richConversation();

    $this->actingAs($member, 'web');

    $response = $this->getJson("/api/conversations/{$conversation->id}/messages")->assertOk();

    expectNoLeakedFields($response, ['email']);

    $messages = collect($response->json('data'));
    $photo = $messages->firstWhere('type', 'image');
    $first = $messages->firstWhere('body', 'first');

    expect(array_keys($photo['attachments'][0]))->toEqualCanonicalizing(['id', 'file_type', 'file_size', 'url', 'thumbnail_url'])
        ->and(array_keys($first['reactions'][0]))->toEqualCanonicalizing(['id', 'message_id', 'user_id', 'emoji'])
        ->and(array_keys($first['sender']))->toEqualCanonicalizing(['id', 'name', 'avatar_url']);
});

it('never leaks private fields in search results', function () {
    [, $member] = richConversation();

    $this->actingAs($member, 'web');

    $response = $this->getJson('/api/conversations/search?q=Alice')->assertOk()->assertJsonCount(1);

    expectNoLeakedFields($response, ['email']);
});

it('shows the email only on the self endpoint', function () {
    $user = makeUser();

    $this->actingAs($user, 'web');

    $response = $this->getJson('/api/user')->assertOk()->assertJsonPath('email', $user->email);

    expect(array_keys($response->json()))->toEqualCanonicalizing(['id', 'name', 'email', 'avatar_url', 'created_at']);
});

it('returns 201 for a new private conversation and 200 for an existing one', function () {
    $user = makeUser();
    $other = makeUser();

    $this->actingAs($user, 'web');

    $payload = ['type' => 'private', 'participant_ids' => [$other->id]];

    $this->postJson('/api/conversations', $payload)->assertCreated();
    $this->postJson('/api/conversations', $payload)->assertOk();

    expect(Conversation::where('type', 'private')->count())->toBe(1);
});

it('broadcasts messages in the same shape as the REST resource, without private fields', function () {
    [$admin, , $conversation, $photo] = richConversation();

    $payload = (new MessageSent($photo))->broadcastWith();
    $rest = (new MessageResource($photo->load(['sender', 'replyTo', 'attachments', 'reactions'])))->resolve();

    expect(array_keys($payload['message']))->toEqualCanonicalizing(array_keys($rest))
        ->and(json_encode($payload))->not->toContain('"email"')
        ->and(json_encode($payload))->not->toContain('"file_path"');
});