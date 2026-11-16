<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('has no public registration', function () {
    get('/register')->assertNotFound();
    post('/register', [
        'name' => 'Intrus',
        'email' => 'intrus@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();
});

it('does not register the Fortify features that were not asked for', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'verification.notice',
    'verification.verify',
    'verification.send',
    'user-profile-information.update',
    'user-password.update',
    'password.confirm',
    'two-factor.login',
    'two-factor.enable',
    'passkey.login',
    'passkey.store',
]);
