<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('reverb:start', 'reverb');
        DevCommands::artisan('queue:work', 'queue');

        DevCommands::only('server', 'reverb', 'queue');

         RateLimiter::for('send-message', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()->id);
        });
    }
}
