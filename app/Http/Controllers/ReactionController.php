<?php

namespace App\Http\Controllers;

use App\Events\ReactionToggled;
use App\Http\Resources\ReactionResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReactionController extends Controller
{
     public function toggle(Request $request, Message $message)
    {
        Gate::authorize('react', $message);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:8'],
        ]);

        $existing = $message->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();

            broadcast(new ReactionToggled(
                (string) $message->id,
                (string) $message->conversation_id,
                (string) $request->user()->id,
                $validated['emoji'],
                'removed'
            ))->toOthers();

            return response()->json(['status' => 'removed']);
        }

        $reaction = $message->reactions()->create([
            'user_id' => $request->user()->id,
            'emoji' => $validated['emoji'],
        ]);

        broadcast(new ReactionToggled(
            (string) $message->id,
            (string) $message->conversation_id,
            (string) $request->user()->id,
            $validated['emoji'],
            'added'
        ))->toOthers();

        return response()->json(['status' => 'added', 'reaction' => (new ReactionResource($reaction))->resolve()], 201);
    }
}
