<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    public const TOO_MANY_ACCOUNT_REQUESTS = 'Trop de demandes envoyées. Veuillez réessayer plus tard.';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        $this->configureRateLimiting();
    }

    /**
     * The public account request form is only protected by this per IP limit.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('account-request', fn (Request $request): Limit => Limit::perHour(5)
            ->by((string) $request->ip())
            ->response(fn (): Response => back()
                ->withInput($request->only('name', 'email', 'message'))
                ->withErrors(['email' => self::TOO_MANY_ACCOUNT_REQUESTS])));
    }
}
