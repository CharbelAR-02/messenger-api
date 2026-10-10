<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsToParticipants;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionToggled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, BroadcastsToParticipants;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $messageId,
        public string $conversationId,
        public string $userId,
        public string $emoji,
        public string $status, // 'added' | 'removed'
    )
    {
        //
    }

     public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'emoji' => $this->emoji,
            'status' => $this->status,
        ];
    }
}
