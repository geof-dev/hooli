<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // A person joins once; a few tries a minute leaves room for a typo.
        RateLimiter::for('waitlist', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
    }
}
