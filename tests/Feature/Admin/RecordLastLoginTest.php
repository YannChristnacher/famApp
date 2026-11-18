<?php

use App\Listeners\Auth\RecordLastLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('listens to the login event', function () {
    Event::fake();

    Event::assertListening(Login::class, RecordLastLogin::class);
});

it('records the time of the login on the member', function () {
    freezeTime();
    $user = User::factory()->create(['last_login_at' => null]);

    event(new Login('web', $user, false));

    expect($user->fresh()?->last_login_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('records the login made through the sign-in form', function () {
    freezeTime();
    $user = User::factory()->create(['password' => 'secret-password']);

    post(route('login.store'), ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect();

    expect($user->fresh()?->last_login_at?->toIso8601String())->toBe(now()->toIso8601String());
});

it('does not record a failed sign-in', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    expect($user->fresh()?->last_login_at)->toBeNull();
});

it('stores no network data about the login', function () {
    $columns = Schema::getColumnListing('users');

    expect($columns)->toContain('last_login_at')
        ->and($columns)->not->toContain('last_login_ip')
        ->and($columns)->not->toContain('last_login_user_agent');
});

it('does not share the last login time with the front end', function () {
    $user = User::factory()->create(['last_login_at' => now()]);
    actingAs($user);

    get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', fn (Collection $shared) => $shared->keys()->all() === ['id', 'name', 'email', 'role']));
});
