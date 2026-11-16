<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;

it('renders the French login page for guests', function () {
    get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

it('redirects authenticated users away from the login page', function () {
    actingAs(User::factory()->create());

    get(route('login'))->assertRedirect(route('home'));
});

it('logs a user in with valid credentials and redirects to home', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect(route('home'));

    assertAuthenticatedAs($user);
});

it('forces a full page visit to home after an Inertia login so the authenticated routes are loaded', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    withHeaders(['X-Inertia' => 'true'])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'));

    assertAuthenticatedAs($user);
});

it('redirects to the page the user was trying to reach after login', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    get('/?onglet=livres')->assertRedirect(route('login'));

    post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect()
        ->assertHeader('Location', config('app.url').'/?onglet=livres');
});

it('sets the remember me cookie when asked to', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password', 'remember' => 'on'])
        ->assertCookie(Auth::guard('web')->getRecallerName());

    expect($user->fresh()?->remember_token)->not->toBeNull();
});

it('does not set the remember me cookie by default', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());
});

it('rejects a wrong password with a French error message', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent pas à nos enregistrements.']);

    assertGuest();
});

it('rejects an unknown email with the same French error message', function () {
    from(route('login'))
        ->post(route('login.store'), ['email' => 'inconnu@example.com', 'password' => 'whatever'])
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent pas à nos enregistrements.']);

    assertGuest();
});

it('requires an email and a password', function () {
    from(route('login'))
        ->post(route('login.store'), [])
        ->assertSessionHasErrors(['email', 'password']);
});

it('locks the login out after five failed attempts, even with the right password', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors('email');

    $error = session('errors')->first('email');
    expect($error)->toStartWith('Tentatives de connexion trop nombreuses.');
    assertGuest();
});
