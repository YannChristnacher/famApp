<?php

use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Core\HomeController;
use Illuminate\Support\Facades\Route;

// Fortify registers the POST routes (login, logout, password reset); these are the pages.
Route::middleware('guest')->controller(AuthPageController::class)->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::get('/forgot-password', 'forgotPassword')->name('password.request');
    Route::get('/reset-password/{token}', 'resetPassword')->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
});
