<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('signs out a session whose password has changed elsewhere', function () {
    $user = User::factory()->create();
    actingAs($user);
    get(route('home'))->assertOk();

    $user->forceFill(['password' => 'nouveau-mot-de-passe'])->save();

    get(route('home'))->assertRedirect(route('login'));
    assertGuest();
});

it('keeps the session when the password has not changed', function () {
    actingAs(User::factory()->create());

    get(route('home'))->assertOk();
    get(route('home'))->assertOk();
});

it('renews the remember token when the password is reset, so remembered devices are signed out', function () {
    $user = User::factory()->create(['remember_token' => 'ancien-jeton']);
    $token = Password::broker()->createToken($user);

    post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertRedirect(route('login'));

    expect($user->fresh()?->remember_token)->not->toBe('ancien-jeton');
});
