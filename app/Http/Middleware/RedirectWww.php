<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWww
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with($request->getHost(), 'www.')) {
            return redirect()->to($request->getScheme().'://'.substr($request->getHttpHost(), 4).$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
