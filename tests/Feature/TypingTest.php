<?php

use App\Events\UserTyping;
use Illuminate\Support\Facades\Event;


it('lets a member signal typing and broadcasts it', function () {
    Event::fake([UserTyping::class]);

    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/typing")->assertNoContent();

    Event::assertDispatched(UserTyping::class, fn ($event) => $event->conversationId === $conversation->id
        && $event->userId === $member->id);
});

it('returns 404 to non-members and broadcasts nothing', function () {
    Event::fake([UserTyping::class]);

    $conversation = makeConversation(makeUser(), [makeUser()]);

    $this->actingAs(makeUser(), 'web');

    $this->postJson("/api/conversations/{$conversation->id}/typing")->assertNotFound();

    Event::assertNotDispatched(UserTyping::class);
});

it('returns 404 to someone who left the conversation', function () {
    Event::fake([UserTyping::class]);

    $admin = makeUser();
    $leaver = makeUser();
    $conversation = makeConversation($admin, [$leaver]);
    participantOf($conversation, $leaver)->forceFill(['left_at' => now()])->save();

    $this->actingAs($leaver, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/typing")->assertNotFound();

    Event::assertNotDispatched(UserTyping::class);
});

it('limits typing signals to 60 per minute', function () {
    Event::fake([UserTyping::class]);

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    foreach (range(1, 60) as $i) {
        $this->postJson("/api/conversations/{$conversation->id}/typing")->assertNoContent();
    }

    $this->postJson("/api/conversations/{$conversation->id}/typing")->assertTooManyRequests();
});

it('still answers 204 when broadcasting fails', function () {
    Event::listen(UserTyping::class, fn () => throw new RuntimeException('Reverb is down'));

    $admin = makeUser();
    $conversation = makeConversation($admin, [makeUser()]);

    $this->actingAs($admin, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/typing")->assertNoContent();
});