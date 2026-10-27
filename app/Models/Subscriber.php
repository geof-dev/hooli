<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone on the waitlist. */
class Subscriber extends Model
{
    protected $fillable = ['email', 'locale', 'situation', 'utm_source', 'utm_campaign'];
}
