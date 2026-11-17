<?php

namespace App\Providers;

use App\Support\ClientNetwork;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every rate limiter of the application, keyed by client network (see
 * ClientNetwork). A refused request goes back to the form with a French error
 * under the email field instead of a bare 429; Inertia keeps what was typed.
 * The JSON endpoints of signed-in members are limited per member instead.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public const TOO_MANY_ATTEMPTS = 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.';

    public const TOO_MANY_LOOKUPS = 'Trop de recherches. Veuillez patienter une minute avant de réessayer.';

    public const TOO_MANY_ACCOUNT_REQUESTS = 'Trop de demandes envoyées. Veuillez réessayer plus tard.';

    public function boot(): void
    {
        // On top of Fortify's lockout after five failed logins for the same
        // email and IP (config fortify.limiters.login = null).
        RateLimiter::for('login', fn (Request $request) => $this->perClient(Limit::perMinute(20), $request, self::TOO_MANY_ATTEMPTS));
        RateLimiter::for('password-reset-link', fn (Request $request) => $this->perClient(Limit::perMinute(5), $request, self::TOO_MANY_ATTEMPTS));
        RateLimiter::for('password-reset', fn (Request $request) => $this->perClient(Limit::perMinute(5), $request, self::TOO_MANY_ATTEMPTS));

        // The public account request form is only protected by this limit.
        RateLimiter::for('account-request', fn (Request $request) => $this->perClient(Limit::perHour(5), $request, self::TOO_MANY_ACCOUNT_REQUESTS));

        // Searches on Google Books (JSON, signed-in members): spares the quota of the API key.
        RateLimiter::for('google-books', fn (Request $request) => Limit::perMinute(10)
            ->by('user:'.$request->user()?->getAuthIdentifier())
            ->response(fn (): JsonResponse => response()->json(['message' => self::TOO_MANY_LOOKUPS], Response::HTTP_TOO_MANY_REQUESTS)));
    }

    private function perClient(Limit $limit, Request $request, string $message): Limit
    {
        return $limit
            ->by(ClientNetwork::rateLimitKey($request->ip()))
            ->response(fn (): Response => back()->withErrors(['email' => $message]));
    }
}
