<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;

it('logs the user out and sends them back to the login page', function () {
    actingAs(User::factory()->create());

    post(route('logout'))->assertRedirect(route('login'));

    assertGuest();
});

it('forces a full page visit to the login page after an Inertia logout', function () {
    actingAs(User::factory()->create());

    withHeaders(['X-Inertia' => 'true'])
        ->post(route('logout'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));

    assertGuest();
});

it('does not let guests call logout', function () {
    post(route('logout'))->assertRedirect(route('login'));
});
