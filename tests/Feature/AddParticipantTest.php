<?php

use App\Models\Participant;

it('keeps an admin as admin when they are added again, including themselves', function () {
    $admin = makeUser();
    $otherAdmin = makeUser();
    $conversation = makeConversation($admin, [$otherAdmin]);
    participantOf($conversation, $otherAdmin)->forceFill(['role' => 'admin'])->save();

    $this->actingAs($admin, 'web');

    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $otherAdmin->id])->assertOk();
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $admin->id])->assertOk();

    expect(participantOf($conversation, $otherAdmin)->role)->toBe('admin')
        ->and(participantOf($conversation, $admin)->role)->toBe('admin');
});

it('does not touch the read pointer of an active member', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    $first = makeMessage($conversation, $admin);
    makeMessage($conversation, $admin);
    setReadPointer($conversation, $member, $first);

    $this->actingAs($admin, 'web');
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $member->id])->assertOk();

    expect(participantOf($conversation, $member)->last_read_message_id)->toBe($first->id);
});

it('starts a new member at the latest message so history is not unread', function () {
    $admin = makeUser();
    $newcomer = makeUser();
    $conversation = makeConversation($admin);
    makeMessage($conversation, $admin);
    $latest = makeMessage($conversation, $admin);

    $this->actingAs($admin, 'web');
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $newcomer->id])->assertOk();

    $participant = participantOf($conversation, $newcomer);
    expect($participant->role)->toBe('member')
        ->and($participant->last_read_message_id)->toBe($latest->id);
});

it('resets role, pointer and left_at when someone who left is added again', function () {
    $admin = makeUser();
    $member = makeUser();
    $conversation = makeConversation($admin, [$member]);
    participantOf($conversation, $member)->forceFill(['left_at' => now()->subDay()])->save();
    $latest = makeMessage($conversation, $admin);

    $this->actingAs($admin, 'web');
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $member->id])->assertOk();

    $participant = participantOf($conversation, $member);
    expect($participant->left_at)->toBeNull()
        ->and($participant->role)->toBe('member')
        ->and($participant->last_read_message_id)->toBe($latest->id);
});

it('only lets group admins add members', function () {
    $admin = makeUser();
    $member = makeUser();
    $outsider = makeUser();
    $conversation = makeConversation($admin, [$member]);

    $this->actingAs($member, 'web');
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $outsider->id])->assertForbidden();

    expect(Participant::where('conversation_id', $conversation->id)->where('user_id', $outsider->id)->exists())->toBeFalse();
});

it('never lets a private conversation gain members', function () {
    $a = makeUser();
    $b = makeUser();
    $outsider = makeUser();
    $conversation = makeConversation($a, [$b], 'private');

    $this->actingAs($a, 'web');
    $this->postJson("/api/conversations/{$conversation->id}/participants", ['user_id' => $outsider->id])->assertForbidden();
});