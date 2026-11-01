<?php

it('hides a conversation from non-members with a 404', function () {
    $conversation = makeConversation(makeUser(), [makeUser()]);

    $this->actingAs(makeUser(), 'web');

    $this->getJson("/api/conversations/{$conversation->id}")
        ->assertNotFound();
});

it('hides a conversation from someone who left it', function () {
    $admin = makeUser();
    $leaver = makeUser();
    $conversation = makeConversation($admin, [$leaver]);

    participantOf($conversation, $leaver)
        ->forceFill(['left_at' => now()])
        ->save();

    $this->actingAs($leaver, 'web');

    $this->getJson("/api/conversations/{$conversation->id}")
        ->assertNotFound();
});

it('shows participants without emails, and personal settings only on your own row', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');

    $response = $this->getJson("/api/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonCount(2, 'participants')
        ->assertJsonMissingPath('participants.0.user.email')
        ->assertJsonMissingPath('participants.1.user.email');

    $participants = collect($response->json('participants'));

    expect($participants->firstWhere('user_id', $member->id))->toHaveKey('muted_at')
        ->and($participants->firstWhere('user_id', $admin->id))->not->toHaveKey('muted_at');
});

it('prevents group members from renaming a group', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');

    $this->patchJson("/api/conversations/{$conversation->id}", [
        'name' => 'Hacked',
    ])->assertForbidden();
});

it('allows group admins to rename a group', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($admin, 'web');

    $this->patchJson("/api/conversations/{$conversation->id}", [
        'name' => 'Renamed',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed');

    expect($conversation->fresh()->name)->toBe('Renamed');
});

it('lets a member leave but not remove someone else', function () {
    $admin = makeUser();
    $first = makeUser();
    $second = makeUser();
    $conversation = makeConversation($admin, [$first, $second]);

    $this->actingAs($first, 'web');

    $this->deleteJson("/api/conversations/{$conversation->id}/participants/{$second->id}")->assertForbidden();
    $this->deleteJson("/api/conversations/{$conversation->id}/participants/{$first->id}")->assertNoContent();

    expect(participantOf($conversation, $first)->left_at)->not->toBeNull()
        ->and(participantOf($conversation, $second)->left_at)->toBeNull();
});

it('lets an admin remove a member', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/conversations/{$conversation->id}/participants/{$member->id}")->assertNoContent();

    expect(participantOf($conversation, $member)->left_at)->not->toBeNull();
});