<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => $this->role,
            'last_read_message_id' => $this->last_read_message_id,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            // Mute / pin / archive are personal: only the owner sees them.
            $this->mergeWhen($this->user_id === $request->user()?->id, [
                'muted_at' => $this->muted_at,
                'pinned_at' => $this->pinned_at,
                'archived_at' => $this->archived_at,
            ]),
        ];
    }
}
