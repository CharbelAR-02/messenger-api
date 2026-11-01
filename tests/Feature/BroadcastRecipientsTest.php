<?php

use App\Events\MessageSent;
use App\Events\PresenceChanged;
use App\Events\UserTyping;

it('sends new messages only to active participants', function () {
    $sender = makeUser();
    $member = makeUser();
    $leaver = makeUser();
    $conversation = makeConversation($sender, [$member, $leaver]);
    participantOf($conversation, $leaver)->forceFill(['left_at' => now()])->save();
    $message = makeMessage($conversation, $sender);

    $channels = channelNames((new MessageSent($message))->broadcastOn());

    expect($channels)->toEqualCanonicalizing([userChannel($sender), userChannel($member)]);
});

it('does not tell the typist that they are typing', function () {
    $typist = makeUser();
    $other = makeUser();
    $conversation = makeConversation($typist, [$other]);

    $channels = channelNames((new UserTyping($conversation->id, $typist->id))->broadcastOn());

    expect($channels)->toBe([userChannel($other)]);
});

it('announces presence only to people who share an active conversation, once each', function () {
    $subject = makeUser();
    $sharesOne = makeUser();
    $sharesTwo = makeUser();
    $leftShared = makeUser();
    $stranger = makeUser();

    makeConversation($subject, [$sharesOne, $sharesTwo]);
    makeConversation($sharesTwo, [$subject], 'private'); // second shared conversation
    $withLeaver = makeConversation($subject, [$leftShared]);
    participantOf($withLeaver, $leftShared)->forceFill(['left_at' => now()])->save();
    makeConversation($stranger, [makeUser()]);

    $channels = channelNames((new PresenceChanged($subject->id, true, now()->toIso8601String()))->broadcastOn());

    expect($channels)->toEqualCanonicalizing([userChannel($sharesOne), userChannel($sharesTwo)]);
});