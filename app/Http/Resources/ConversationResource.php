<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'type' => $this->type,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            // My own membership, taken from the pivot when the list comes from user->conversations()
            'membership' => $this->whenPivotLoaded('participants', fn () => [
                'role' => $this->pivot->role,
                'last_read_message_id' => $this->pivot->last_read_message_id,
                'muted_at' => $this->pivot->muted_at,
                'pinned_at' => $this->pivot->pinned_at,
                'archived_at' => $this->pivot->archived_at,
            ]),
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
