<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * A full page visit (not an Inertia visit) so the page prints the route list of
 * signed-in users and a fresh CSRF token.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return Inertia::location($request->session()->pull('url.intended', route('home')));
    }
}
