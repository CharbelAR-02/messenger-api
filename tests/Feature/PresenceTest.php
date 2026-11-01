<?php

use App\Events\PresenceChanged;
use Illuminate\Support\Facades\Event;

it('flips an offline user online on the first heartbeat and tells their contacts once', function () {
    Event::fake([PresenceChanged::class]);

    $user = makeUser(['is_online' => false]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/presence/heartbeat')->assertNoContent();

    $fresh = $user->fresh();
    expect($fresh->is_online)->toBeTrue()->and($fresh->last_seen_at)->not->toBeNull();

    Event::assertDispatchedTimes(PresenceChanged::class, 1);
    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->userId === $user->id && $event->isOnline === true);
});

it('refreshes last_seen_at on later heartbeats without broadcasting again', function () {
    Event::fake([PresenceChanged::class]);

    $user = makeUser(['is_online' => true, 'last_seen_at' => now()->subHour()]);

    $this->actingAs($user, 'web');

    $this->postJson('/api/presence/heartbeat')->assertNoContent();

    expect($user->fresh()->last_seen_at->isAfter(now()->subMinute()))->toBeTrue();
    Event::assertNotDispatched(PresenceChanged::class);
});

it('marks users offline when their heartbeat is stale and broadcasts it', function () {
    Event::fake([PresenceChanged::class]);

    $stale = makeUser(['is_online' => true, 'last_seen_at' => now()->subMinutes(5)]);

    $this->artisan('presence:sweep')->assertSuccessful();

    expect($stale->fresh()->is_online)->toBeFalse();
    Event::assertDispatched(PresenceChanged::class, fn ($event) => $event->userId === $stale->id && $event->isOnline === false);
});

it('leaves users with a fresh heartbeat online', function () {
    Event::fake([PresenceChanged::class]);

    $fresh = makeUser(['is_online' => true, 'last_seen_at' => now()->subSeconds(10)]);

    $this->artisan('presence:sweep')->assertSuccessful();

    expect($fresh->fresh()->is_online)->toBeTrue();
    Event::assertNotDispatched(PresenceChanged::class);
});

it('ignores users who are already offline', function () {
    Event::fake([PresenceChanged::class]);

    makeUser(['is_online' => false, 'last_seen_at' => now()->subHour()]);

    $this->artisan('presence:sweep')->assertSuccessful();

    Event::assertNotDispatched(PresenceChanged::class);
});

it('limits heartbeats to 20 per minute', function () {
    Event::fake([PresenceChanged::class]);

    $this->actingAs(makeUser(), 'web');

    foreach (range(1, 20) as $i) {
        $this->postJson('/api/presence/heartbeat')->assertNoContent();
    }

    $this->postJson('/api/presence/heartbeat')->assertTooManyRequests();
});

it('requires authentication for heartbeats', function () {
    $this->postJson('/api/presence/heartbeat')->assertUnauthorized();
});