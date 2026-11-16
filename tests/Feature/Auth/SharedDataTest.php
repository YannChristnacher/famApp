<?php

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shares only the id, name, email and role of the authenticated user', function () {
    $user = User::factory()->admin()->create(['name' => 'Marie', 'email' => 'marie@example.com']);
    actingAs($user);

    get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => 'Marie',
                'email' => 'marie@example.com',
                'role' => Role::Admin->value,
            ]));
});

it('never leaks sensitive user fields in the page', function () {
    $user = User::factory()->create(['remember_token' => 'jeton-secret']);
    actingAs($user);

    get(route('home'))
        ->assertOk()
        ->assertDontSee((string) $user->password, false)
        ->assertDontSee('jeton-secret', false)
        ->assertDontSee('remember_token', false)
        ->assertDontSee('email_verified_at', false);
});

it('shares a null user with guests', function () {
    get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('shares the session status message', function () {
    session()->flash('status', 'Votre mot de passe a été réinitialisé !');

    get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'Votre mot de passe a été réinitialisé !'));
});
