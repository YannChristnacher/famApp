<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    get(route('home'))->assertRedirect(route('login'));
});

it('shows the home page to authenticated users', function () {
    actingAs(User::factory()->create());

    get(route('home'))->assertOk();
});
