<?php

namespace App\Events;

use App\Models\Participant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $conversationId,
        public string $userId,)
    {

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return Participant::where('conversation_id', $this->conversationId)
            ->whereNull('left_at')
            ->where('user_id', '!=', $this->userId)
            ->pluck('user_id')
            ->map(fn ($id) => new PrivateChannel("user.{$id}"))
            ->all();
    }

     public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
        ];
    }
}
