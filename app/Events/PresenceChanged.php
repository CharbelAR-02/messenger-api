<?php

namespace App\Events;

use App\Models\Participant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $userId,
        public bool $isOnline,
        public ?string $lastSeenAt,)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
         return Participant::whereIn('conversation_id', fn ($q) => $q
                ->select('conversation_id')
                ->from('participants')
                ->where('user_id', $this->userId)
                ->whereNull('left_at'))
            ->whereNull('left_at')
            ->where('user_id', '!=', $this->userId)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => new PrivateChannel("user.{$id}"))
            ->all();
    }

      public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'is_online' => $this->isOnline,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }
}
