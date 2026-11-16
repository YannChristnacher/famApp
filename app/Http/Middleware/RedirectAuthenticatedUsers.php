<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "guest" middleware: a signed-in user reaching a guest page is sent home with
 * a full page visit when the request comes from Inertia, so the page prints
 * the route list of signed-in users.
 */
class RedirectAuthenticatedUsers extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return Inertia::location($this->redirectTo($request) ?? route('home'));
            }
        }

        return $next($request);
    }
}
