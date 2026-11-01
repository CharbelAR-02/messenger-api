<?php


use App\Events\ReactionToggled;
use App\Models\Reaction;
use Illuminate\Support\Facades\Event;

it('adds a reaction and broadcasts it', function () {
    Event::fake([ReactionToggled::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])
        ->assertCreated()
        ->assertJsonPath('status', 'added')
        ->assertJsonPath('reaction.emoji', '👍')
        ->assertJsonPath('reaction.user_id', $member->id)
        ->assertJsonMissingPath('reaction.created_at');

    expect(Reaction::where('message_id', $message->id)->count())->toBe(1);

    Event::assertDispatched(ReactionToggled::class, fn ($event) => $event->status === 'added'
        && $event->emoji === '👍'
        && $event->messageId === $message->id
        && $event->userId === $member->id);
});

it('removes the reaction when the same emoji is sent twice', function () {
    Event::fake([ReactionToggled::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertCreated();
    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])
        ->assertOk()
        ->assertJsonPath('status', 'removed');

    expect(Reaction::where('message_id', $message->id)->count())->toBe(0);

    Event::assertDispatched(ReactionToggled::class, fn ($event) => $event->status === 'removed');
});

it('lets one user stack different emojis on a message', function () {
    Event::fake([ReactionToggled::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs($member, 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertCreated();
    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '❤️'])->assertCreated();

    expect(Reaction::where('message_id', $message->id)->count())->toBe(2);
});

it('lets two users use the same emoji on a message', function () {
    Event::fake([ReactionToggled::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $message = makeMessage($conversation, $admin);
    Reaction::create(['message_id' => $message->id, 'user_id' => $admin->id, 'emoji' => '👍']);

    $this->actingAs($member, 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertCreated();

    expect(Reaction::where('message_id', $message->id)->count())->toBe(2);
});


it('forbids reacting to messages in conversations you are not in', function () {
    Event::fake([ReactionToggled::class]);

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs(makeUser(), 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertForbidden();

    expect(Reaction::count())->toBe(0);
    Event::assertNotDispatched(ReactionToggled::class);
});

it('validates the emoji field', function () {
    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);
    $message = makeMessage($conversation, $admin);

    $this->actingAs($admin, 'web');

    $this->postJson("/api/messages/{$message->id}/reactions", [])->assertJsonValidationErrors('emoji');
    $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => str_repeat('a', 9)])->assertJsonValidationErrors('emoji');
});