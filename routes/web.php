<?php

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Core\HomeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

// Authentication: Fortify controllers, routes declared here (Fortify::ignoreRoutes())
// so that only the needed ones exist, each with its rate limiter.
Route::middleware('guest')->group(function () {
    Route::controller(AuthPageController::class)->group(function () {
        Route::get('/login', 'login')->name('login');
        Route::get('/forgot-password', 'forgotPassword')->name('password.request');
        Route::get('/reset-password/{token}', 'resetPassword')->name('password.reset');
    });

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset-link')
        ->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', HomeController::class)->name('home');
});
