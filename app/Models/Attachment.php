<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
     use HasUuids;

    protected $fillable = ['message_id', 'file_path', 'thumbnail_path', 'file_type', 'file_size'];


    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute('attachments.show', now()->addMinutes(30), ['attachment' => $this->id]);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path
            ? URL::temporarySignedRoute('attachments.thumbnail', now()->addMinutes(30), ['attachment' => $this->id])
            : null;
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
