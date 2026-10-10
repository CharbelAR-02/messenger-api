<?php

namespace App\Events\Concerns;

use App\Models\Participant;
use Illuminate\Broadcasting\PrivateChannel;

trait BroadcastsToParticipants
{
    public function broadcastOn(): array
    {
        return Participant::where('conversation_id', $this->conversationId)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->map(fn ($id) => new PrivateChannel("user.{$id}"))
            ->all();
    }
}