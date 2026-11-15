<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $request->cookie('rawijli_token')) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie('rawijli_token'));
        }

        return $next($request);
    }
}
