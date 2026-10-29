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

        // The app looks up every scan, and a shopper scans fast. Each label read is a paid call to Claude: twenty an
        // hour is a long shop.
        RateLimiter::for('lookups', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('labels', fn (Request $request) => Limit::perHour(20)->by($request->ip()));
    }
}
