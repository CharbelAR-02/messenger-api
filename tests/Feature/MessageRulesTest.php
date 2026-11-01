<?php

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Event;

it('lets a member send a text message and broadcasts it', function () {
    Event::fake([MessageSent::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');

    $response = $this->postJson("/api/conversations/{$conversation->id}/messages", [
        'type' => 'text',
        'body' => 'hi',
    ])
        ->assertCreated()
        ->assertJsonPath('body', 'hi')
        ->assertJsonPath('user_id', $member->id);

    Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->id === $response->json('id'));
});

it('refuses messages from non-members', function () {
    Event::fake([MessageSent::class]);

    $conversation = makeConversation(makeUser(), [makeUser()]);

    $this->actingAs(makeUser(), 'web');

    $this->postJson("/api/conversations/{$conversation->id}/messages", ['type' => 'text', 'body' => 'let me in'])
        ->assertForbidden();

    Event::assertNotDispatched(MessageSent::class);
});

it('requires a body for text messages and broadcasts nothing when invalid', function () {
    Event::fake([MessageSent::class]);

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/messages", ['type' => 'text'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');

    Event::assertNotDispatched(MessageSent::class);
});

it('only accepts a reply target from the same conversation', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $elsewhere = makeConversation($admin, [$member]);
    $foreign = makeMessage($elsewhere, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/messages", [
        'type' => 'text',
        'body' => 'hi',
        'reply_to_id' => $foreign->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reply_to_id');
});

it('limits sending to 30 messages per minute', function () {
    Event::fake([MessageSent::class]);

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    foreach (range(1, 30) as $i) {
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['type' => 'text', 'body' => "message {$i}"])
            ->assertCreated();
    }

    $this->postJson("/api/conversations/{$conversation->id}/messages", ['type' => 'text', 'body' => 'one too many'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('lets the sender edit their own message and marks it edited', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member, ['body' => 'old']);

    $this->actingAs($member, 'web');

    $this->patchJson("/api/messages/{$message->id}", ['body' => 'new'])
        ->assertOk()
        ->assertJsonPath('body', 'new');

    expect($message->fresh()->edited_at)->not->toBeNull();
});

it('forbids editing someone else\'s message', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member, ['body' => 'mine']);

    $this->actingAs($admin, 'web');

    $this->patchJson("/api/messages/{$message->id}", ['body' => 'hijacked'])->assertForbidden();

    expect($message->fresh()->body)->toBe('mine');
});

it('forbids editing your own message after leaving the conversation', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member);
    participantOf($conversation, $member)->forceFill(['left_at' => now()])->save();

    $this->actingAs($member, 'web');

    $this->patchJson("/api/messages/{$message->id}", ['body' => 'too late'])->assertForbidden();
});

it('does not allow editing voice messages', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member, ['type' => 'voice', 'body' => null]);

    $this->actingAs($member, 'web');

    $this->patchJson("/api/messages/{$message->id}", ['body' => 'caption'])->assertUnprocessable();
});

it('requires a body when editing a text message', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member);

    $this->actingAs($member, 'web');

    $this->patchJson("/api/messages/{$message->id}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('lets the sender delete their own message for everyone and broadcasts it', function () {
    Event::fake([MessageDeleted::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member);

    $this->actingAs($member, 'web');

    $this->deleteJson("/api/messages/{$message->id}")->assertNoContent();

    $this->assertSoftDeleted($message);
    Event::assertDispatched(MessageDeleted::class, fn ($event) => $event->messageId === $message->id
        && $event->conversationId === $conversation->id);
});

it('forbids deleting someone else\'s message', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $member);

    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/messages/{$message->id}")->assertForbidden();

    $this->assertNotSoftDeleted($message);
});

it('hides a message from me only', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/messages/{$message->id}/hide")->assertNoContent();

    $this->getJson("/api/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonMissing(['id' => $message->id]);
});

it('keeps a message hidden by someone else visible to me', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);
    $message->hiddenFor()->attach($member->id);

    $this->actingAs($admin, 'web');

    $this->getJson("/api/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonFragment(['id' => $message->id]);
});