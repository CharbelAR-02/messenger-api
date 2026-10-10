<?php

// use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
//     $conversation = Conversation::find($conversationId);

//     if (! $conversation) {
//         return false;
//     }

//     return $conversation->participants()
//         ->where('user_id', $user->id)
//         ->whereNull('left_at')
//         ->exists();
// });
Broadcast::channel('user.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});