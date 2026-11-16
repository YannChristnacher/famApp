<?php

namespace App\Providers;

use App\Support\ClientNetwork;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every rate limiter of the application, keyed by client network (see
 * ClientNetwork). A refused request goes back to the form with a French error
 * under the email field instead of a bare 429; Inertia keeps what was typed.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public const TOO_MANY_ATTEMPTS = 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.';

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
    }

    private function perClient(Limit $limit, Request $request, string $message): Limit
    {
        return $limit
            ->by(ClientNetwork::rateLimitKey($request->ip()))
            ->response(fn (): Response => back()->withErrors(['email' => $message]));
    }
}
