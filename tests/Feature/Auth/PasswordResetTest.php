<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

const NEUTRAL_RESET_LINK_STATUS = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.';

it('renders the forgot password page for guests', function () {
    get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('renders the reset password page with the token and the email', function () {
    get(route('password.reset', ['token' => 'abc123', 'email' => 'marie@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'abc123')
            ->where('email', 'marie@example.com'));
});

it('keeps the password pages for guests only', function () {
    actingAs(User::factory()->create());

    get(route('password.request'))->assertRedirect(route('home'));
    get(route('password.reset', ['token' => 'abc123']))->assertRedirect(route('home'));
});

it('sends the French reset password notification to an existing user', function () {
    Notification::fake();
    $user = User::factory()->create();

    from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', NEUTRAL_RESET_LINK_STATUS);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('answers exactly the same way for an unknown email and sends nothing', function () {
    Notification::fake();

    from(route('password.request'))
        ->post(route('password.email'), ['email' => 'inconnu@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', NEUTRAL_RESET_LINK_STATUS);

    Notification::assertNothingSent();
});

it('answers the same way when a link was requested too recently', function () {
    Notification::fake();
    $user = User::factory()->create();
    post(route('password.email'), ['email' => $user->email]);

    from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', NEUTRAL_RESET_LINK_STATUS);

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});

it('requires a valid email to request a link', function () {
    from(route('password.request'))
        ->post(route('password.email'), ['email' => 'pas-un-email'])
        ->assertSessionHasErrors('email');
});

it('resets the password with a valid token and redirects to the login page', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Votre mot de passe a été réinitialisé !');

    expect(Hash::check('nouveau-mot-de-passe', (string) $user->fresh()?->password))->toBeTrue();
});

it('refuses an invalid token with a French error and keeps the password', function () {
    $user = User::factory()->create(['password' => 'ancien-mot-de-passe']);

    from(route('password.reset', ['token' => 'invalide']))
        ->post(route('password.update'), [
            'token' => 'invalide',
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])
        ->assertSessionHasErrors(['email' => "Ce jeton de réinitialisation du mot de passe n'est pas valide."]);

    expect(Hash::check('ancien-mot-de-passe', (string) $user->fresh()?->password))->toBeTrue();
});

it('requires a confirmed password to reset it', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    from(route('password.reset', ['token' => $token]))
        ->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'autre-chose',
        ])
        ->assertSessionHasErrors('password');
});
