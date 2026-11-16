<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\Auth\InvalidPasswordResetResponse;
use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\LogoutResponse;
use App\Http\Responses\Auth\PasswordResetLinkRequestedResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    public const TOO_MANY_ATTEMPTS = 'Trop de tentatives. Veuillez patienter une minute avant de réessayer.';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The authentication routes are declared in routes/web.php.
        Fortify::ignoreRoutes();

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
        $this->app->bind(FailedPasswordResetResponse::class, InvalidPasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->configureRateLimiting();
    }

    /**
     * Per IP limits, on top of Fortify's lockout after five failed logins for
     * the same email and IP (config fortify.limiters.login = null).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => $this->perIpLimit($request, 20));
        RateLimiter::for('password-reset-link', fn (Request $request) => $this->perIpLimit($request, 5));
        RateLimiter::for('password-reset', fn (Request $request) => $this->perIpLimit($request, 5));
    }

    private function perIpLimit(Request $request, int $attemptsPerMinute): Limit
    {
        return Limit::perMinute($attemptsPerMinute)
            ->by((string) $request->ip())
            ->response(fn (): Response => back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => self::TOO_MANY_ATTEMPTS]));
    }
}
