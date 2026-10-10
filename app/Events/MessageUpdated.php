<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsToParticipants;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsToParticipants;

    public string $conversationId;

    public function __construct(public Message $message)
    {
        $this->conversationId = $message->conversation_id;
    }

     public function broadcastWith(): array
    {
        return ['message' => $this->message->load(['sender', 'replyTo', 'attachments'])];
    }
}
