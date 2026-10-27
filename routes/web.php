<?php

use App\Http\Controllers\GuideController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WaitlistController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Every page, once per language: / and /guides in English, /fr and /fr/guides in French, /et and /et/juhendid in
// Estonian. Names carry the language (en.home, fr.home); localized_route() picks the current one.
foreach (config('hooli.locales') as $locale => $language) {
    Route::prefix($locale === 'en' ? '' : $locale)
        ->middleware(SetLocale::class.':'.$locale)
        ->name($locale.'.')
        ->group(function () use ($language) {
            Route::view('/', 'landing')->name('home');
            Route::get($language['guides'], [GuideController::class, 'index'])->name('guides');
            Route::get($language['guides'].'/{slug}', [GuideController::class, 'show'])->name('guide');

            // The waitlist takes no cookie: the form carries no CSRF token, and spam meets a honeypot and a rate
            // limit instead.
            Route::post('waitlist', WaitlistController::class)->middleware('throttle:waitlist')->name('waitlist.join');
        });
}

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
