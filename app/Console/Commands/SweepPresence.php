<?php

namespace App\Console\Commands;

use App\Events\PresenceChanged;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('presence:sweep')]
#[Description('Mark users offline when their heartbeat has gone stale')]
class SweepPresence extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cutoff = now()->subSeconds(60);

        User::where('is_online', true)
            ->where('last_seen_at', '<', $cutoff)
            ->pluck('id')
            ->each(function ($id) use ($cutoff) {
                // Re-check in the UPDATE so a heartbeat that lands mid-sweep wins.
                $flipped = User::whereKey($id)
                    ->where('is_online', true)
                    ->where('last_seen_at', '<', $cutoff)
                    ->update(['is_online' => false]);

                if ($flipped) {
                    PresenceChanged::dispatch($id, false, User::find($id)->last_seen_at?->toIso8601String());
                }
            });
    }
}
