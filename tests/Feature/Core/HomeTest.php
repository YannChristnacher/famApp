<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('renders the home page through Inertia', function () {
    actingAs(User::factory()->create());

    get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Home')
            ->where('appName', config('app.name'))
        );
});

it('exposes the named routes used by authenticated pages to the front end', function () {
    actingAs(User::factory()->create());

    get('/')
        ->assertOk()
        ->assertSee('const Ziggy=', false)
        ->assertSee('"home":{"uri":"\/"', false)
        ->assertSee('"logout":', false)
        ->assertDontSee('"login":', false)
        ->assertDontSee('"password.request":', false)
        ->assertDontSee('storage.local', false);
});

it('only exposes the authentication routes to guests', function () {
    get(route('login'))
        ->assertOk()
        ->assertSee('const Ziggy=', false)
        ->assertSee('"login":', false)
        ->assertSee('"login.store":', false)
        ->assertSee('"password.request":', false)
        ->assertSee('"password.email":', false)
        ->assertSee('"password.update":', false)
        ->assertDontSee('"home":', false)
        ->assertDontSee('"logout":', false)
        ->assertDontSee('"password.reset":', false);
});

it('only exposes the named routes explicitly allowed for the front end', function () {
    Route::get('/admin/secret', fn () => 'secret')->name('admin.secret');
    Route::getRoutes()->refreshNameLookups();
    actingAs(User::factory()->admin()->create());

    get('/')
        ->assertOk()
        ->assertSee('"home":', false)
        ->assertDontSee('admin.secret', false)
        ->assertDontSee('admin\\/secret', false);

    auth()->logout();

    get(route('login'))
        ->assertOk()
        ->assertDontSee('admin.secret', false)
        ->assertDontSee('admin\\/secret', false);
});

it('keeps the Ziggy groups within the allowlist', function () {
    $allowlist = config('ziggy.only');

    expect(config('ziggy.groups.guest'))->each->toBeIn($allowlist)
        ->and(config('ziggy.groups.auth'))->each->toBeIn($allowlist)
        ->and(array_intersect(config('ziggy.groups.guest'), config('ziggy.groups.auth')))->toBeEmpty();
});
