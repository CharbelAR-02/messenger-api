<?php

namespace App\Http\Controllers;

use App\Events\PresenceChanged;
use App\Models\User;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
     public function heartbeat(Request $request)
    {
        $user = $request->user();

        // Atomic flip: only one concurrent request (e.g. two tabs) can win it.
        $flipped = User::whereKey($user->id)
            ->where('is_online', false)
            ->update(['is_online' => true, 'last_seen_at' => now()]);

        if ($flipped) {
            PresenceChanged::dispatch($user->id, true, now()->toIso8601String());
        } else {
            User::whereKey($user->id)->update(['last_seen_at' => now()]);
        }

        return response()->noContent();
    }
}
