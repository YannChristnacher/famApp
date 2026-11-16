<?php

namespace App\Http\Responses\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest (or an expired session) making an Inertia request is sent to the
 * login page with a full page visit, so the page prints the route list of
 * guests instead of keeping the one of signed-in users.
 */
class UnauthenticatedResponse
{
    public function __invoke(AuthenticationException $exception, Request $request): ?Response
    {
        if (! $request->hasHeader('X-Inertia')) {
            return null;
        }

        if ($request->isMethod('GET')) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return Inertia::location($exception->redirectTo($request) ?? route('login'));
    }
}
