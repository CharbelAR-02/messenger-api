<?php

namespace App\Http\Controllers;

use App\Events\UserTyping;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ConversationTypingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request,Conversation $conversation)
    {

        Gate::authorize('view', $conversation);

        try {
            UserTyping::dispatch($conversation->id, $request->user()->id);
        } catch (Throwable $e) {
            report($e); // typing is ephemeral, so never fail the request over it
        }

        return response()->noContent();
    }
}
