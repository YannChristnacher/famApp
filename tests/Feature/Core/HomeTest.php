<?php

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

it('renders the home page through Inertia', function () {
    get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Home')
            ->where('appName', config('app.name'))
        );
});
