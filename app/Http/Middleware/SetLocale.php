<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Speaks the language the URL asks for: routes/web.php gives each language's routes its own. */
class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);

        return $next($request);
    }
}
