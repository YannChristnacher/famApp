<?php

use Illuminate\Support\Facades\Route;
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

it('exposes the named routes used by the navigation registry to the front end', function () {
    get('/')
        ->assertOk()
        ->assertSee('const Ziggy=', false)
        ->assertSee('"home":{"uri":"\/"', false)
        ->assertDontSee('storage.local', false);
});

it('only exposes the named routes explicitly allowed for the front end', function () {
    Route::get('/admin/secret', fn () => 'secret')->name('admin.secret');
    Route::getRoutes()->refreshNameLookups();

    get('/')
        ->assertOk()
        ->assertSee('"home":', false)
        ->assertDontSee('admin.secret', false)
        ->assertDontSee('admin\\/secret', false);
});
