<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
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
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }
}
