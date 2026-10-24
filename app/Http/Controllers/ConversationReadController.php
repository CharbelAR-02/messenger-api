<?php

namespace App\Http\Controllers;

use App\Events\MessagesRead;
use App\Models\Conversation;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConversationReadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request,Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'message_id' => [
                'required',
                'uuid',
                Rule::exists('messages', 'id')->where('conversation_id', $conversation->id),
            ],
        ]);

        $moved = Participant::where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->where(fn ($q) => $q
                ->whereNull('last_read_message_id')
                ->orWhere('last_read_message_id', '<', $validated['message_id']))
            ->update(['last_read_message_id' => $validated['message_id']]);

        if ($moved) {
            broadcast(new MessagesRead(
                $conversation->id,
                $request->user()->id,
                $validated['message_id'],
            ))->toOthers();
        }

        return response()->noContent();
    }
}
