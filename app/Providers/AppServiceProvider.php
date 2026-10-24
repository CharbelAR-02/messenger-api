<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        RateLimiter::for('typing', fn (Request $request) =>
            Limit::perMinute(60)->by($request->user()->id)
        );

        RateLimiter::for('presence', fn (Request $request) =>
            Limit::perMinute(20)->by($request->user()->id)
        );

        RateLimiter::for('send-message', fn (Request $request) =>
            Limit::perMinute(30)->by($request->user()->id)
        );
    }
}
