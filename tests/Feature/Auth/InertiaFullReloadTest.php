<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

it('forces a full page visit to the login page when a guest makes an Inertia request', function () {
    withHeaders(inertiaHeaders())
        ->get(route('home'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
});

it('remembers the page a guest was visiting through Inertia', function () {
    withHeaders(inertiaHeaders())->get('/?onglet=livres')->assertStatus(409);

    expect(session('url.intended'))->toBe(config('app.url').'/?onglet=livres');
});

it('forces a full page visit when a guest posts through Inertia', function () {
    withHeaders(inertiaHeaders())
        ->post(route('logout'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
});

it('keeps the regular redirect for guests outside Inertia', function () {
    get(route('home'))->assertRedirect(route('login'));
});

it('forces a full page visit to home when a signed-in user reaches a guest page through Inertia', function (string $routeName) {
    actingAs(User::factory()->create());

    withHeaders(inertiaHeaders())
        ->get(route($routeName, ['token' => 'abc']))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'));
})->with(['login', 'password.request', 'password.reset']);

it('keeps the regular redirect for signed-in users outside Inertia', function () {
    actingAs(User::factory()->create());

    get(route('login'))->assertRedirect(route('home'));
});

it('still answers JSON requests from guests with a 401', function () {
    withHeaders(['Accept' => 'application/json'])->get(route('home'))->assertUnauthorized();
});
