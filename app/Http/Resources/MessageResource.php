<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar_url' => $this->sender->avatar_url,
            ]),
            'type' => $this->type,
            'body' => $this->body,
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => new MessageResource($this->whenLoaded('replyTo')),
            'edited_at' => $this->edited_at,
            'created_at' => $this->created_at,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'reactions' => ReactionResource::collection($this->whenLoaded('reactions')),
        ];
    }
}
