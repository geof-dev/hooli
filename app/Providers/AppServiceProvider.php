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

        // The app looks up every scan, and a shopper scans fast. Each photo is a paid call to Claude, and a front may
        // add paid web searches: twenty an hour is a long shop.
        RateLimiter::for('lookups', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('labels', fn (Request $request) => Limit::perHour(20)->by($request->ip()));

        // The labels the app reads on the phone cost us nothing, but each changes what everyone is told.
        RateLimiter::for('contributions', fn (Request $request) => Limit::perHour(30)->by($request->ip()));
    }
}
