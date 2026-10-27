<?php

use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

// The waitlist takes no cookie: the form carries no CSRF token, and spam meets a honeypot and a rate limit instead.
Route::post('/waitlist', WaitlistController::class)->middleware('throttle:waitlist')->name('waitlist.join');
