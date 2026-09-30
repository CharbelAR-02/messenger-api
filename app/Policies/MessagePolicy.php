<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
     private function isActiveMember(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }

    private function isActiveParticipant(User $user, Message $message): bool
    {
        return $this->isActiveMember($user, $message->conversation);
    }

    public function viewAny(User $user, Conversation $conversation): bool
    {
        return $this->isActiveMember($user, $conversation);
    }

    public function view(User $user, Message $message): bool
    {
        return $this->isActiveParticipant($user, $message);
    }

    public function create(User $user, Conversation $conversation): bool
    {
        return $this->isActiveMember($user, $conversation);
    }

    public function update(User $user, Message $message): bool
    {
        return $user->is($message->sender) && $this->isActiveParticipant($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->is($message->sender) && $this->isActiveParticipant($user, $message);
    }

    public function hide(User $user, Message $message): bool
    {
        return $this->isActiveParticipant($user, $message);
    }
}
