<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
     private function activeParticipant(User $user, Conversation $conversation)
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
    }

     public function view(User $user, Conversation $conversation): Response
    {
        return $this->activeParticipant($user, $conversation)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

     public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->type === 'group'
            && $this->activeParticipant($user, $conversation)?->isAdmin() === true;
    }

    public function addParticipant(User $user, Conversation $conversation): bool
    {
        return $this->update($user, $conversation);
    }

     public function removeParticipant(User $user, Conversation $conversation, User $target): bool
    {
        if ($conversation->type !== 'group') {
            return false;
        }

        $actor = $this->activeParticipant($user, $conversation);

        if (! $actor) {
            return false;
        }

        // Anyone can leave; only admins can remove someone else.
        return $user->is($target) || $actor->isAdmin();
    }
}
