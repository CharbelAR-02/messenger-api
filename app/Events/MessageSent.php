<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsToParticipants;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsToParticipants;

    public string $conversationId;

    public function __construct(public Message $message)
    {
        $this->conversationId = $message->conversation_id;
    }

      public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource(
                $this->message->load(['sender', 'replyTo', 'attachments','reactions'])
            ))->resolve(),
        ];
    }
}
