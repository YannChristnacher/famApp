<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * A full page visit so the login page prints the route list of guests.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return Inertia::location(route('login'));
    }
}
