<?php

use App\Events\MessagesRead;
use Illuminate\Support\Facades\Event;

it('moves the read pointer forward and broadcasts it', function () {
    Event::fake([MessagesRead::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    makeMessage($conversation, $admin);
    $latest = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $latest->id])
        ->assertNoContent();

    expect(participantOf($conversation, $member)->last_read_message_id)->toBe($latest->id);

    Event::assertDispatched(MessagesRead::class, fn ($event) => $event->conversationId === $conversation->id
        && $event->userId === $member->id
        && $event->lastReadMessageId === $latest->id);
});


it('never moves the read pointer backwards and does not broadcast', function () {
    Event::fake([MessagesRead::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $older = makeMessage($conversation, $admin);
    $newer = makeMessage($conversation, $admin);
    setReadPointer($conversation, $member, $newer);

    $this->actingAs($member, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $older->id])
        ->assertNoContent();

    expect(participantOf($conversation, $member)->last_read_message_id)->toBe($newer->id);
    Event::assertNotDispatched(MessagesRead::class);
});

it('rejects a message id from another conversation', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $elsewhere = makeConversation($admin, [$member]);
    $foreign = makeMessage($elsewhere, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
});

it('returns 404 to non-members marking a conversation read', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs(makeUser(), 'web');

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $message->id])
        ->assertNotFound();
});

it('returns 404 to someone who left the conversation', function () {
    $admin = makeUser();
    $leaver = makeUser();
    $conversation = makeConversation($admin, [$leaver]);
    $message = makeMessage($conversation, $admin);
    participantOf($conversation, $leaver)->forceFill(['left_at' => now()])->save();

    $this->actingAs($leaver, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $message->id])
        ->assertNotFound();
});

it('counts messages from others that come after my pointer', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $first = makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);
    setReadPointer($conversation, $member, $first);

    $this->actingAs($member, 'web');

    $this->getJson('/api/conversations')->assertOk()->assertJsonPath('0.unread_count', 2);
});

it('drops the unread count to zero after marking the latest message read', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);
    $last = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->getJson('/api/conversations')->assertJsonPath('0.unread_count', 3);

    $this->postJson("/api/conversations/{$conversation->id}/read", ['message_id' => $last->id])
        ->assertNoContent();

    $this->getJson('/api/conversations')->assertJsonPath('0.unread_count', 0);
});

it('never counts my own messages as unread', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);

    $this->actingAs($admin, 'web');

    $this->getJson('/api/conversations')->assertOk()->assertJsonPath('0.unread_count', 0);
});


it('does not count messages I hid or that were deleted for everyone', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    makeMessage($conversation, $admin);
    $hidden = makeMessage($conversation, $admin);
    $deleted = makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);

    $hidden->hiddenFor()->attach($member->id);
    $deleted->delete();

    $this->actingAs($member, 'web');

    $this->getJson('/api/conversations')->assertOk()->assertJsonPath('0.unread_count', 2);
});