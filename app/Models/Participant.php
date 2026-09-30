<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
     use HasUuids;

     protected $fillable = ['conversation_id', 'user_id', 'role','left_at'];

      protected $casts = [
        'muted_at' => 'datetime',
        'pinned_at' => 'datetime',
        'archived_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

     public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

     public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }

      public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }


}
