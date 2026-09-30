<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Attachment extends Model
{
     use HasUuids;

    protected $fillable = ['message_id', 'file_path', 'thumbnail_path', 'file_type', 'file_size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
