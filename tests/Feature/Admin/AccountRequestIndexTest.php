<?php

use App\Models\Auth\AccountRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lists the pending requests first, then the most recent ones', function () {
    $oldApproved = AccountRequest::factory()->approved()->create(['created_at' => now()->subDays(10)]);
    $oldPending = AccountRequest::factory()->create(['created_at' => now()->subDays(5)]);
    $recentRejected = AccountRequest::factory()->rejected()->create(['created_at' => now()->subDay()]);
    $recentPending = AccountRequest::factory()->create(['created_at' => now()->subHours(2)]);
    $newestApproved = AccountRequest::factory()->approved()->create(['created_at' => now()->subHour()]);
    actingAs(User::factory()->admin()->create());

    get(route('admin.account-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AccountRequests/Index')
            ->has('accountRequests', 5)
            ->where('accountRequests.0.id', $recentPending->id)
            ->where('accountRequests.1.id', $oldPending->id)
            ->where('accountRequests.2.id', $newestApproved->id)
            ->where('accountRequests.3.id', $recentRejected->id)
            ->where('accountRequests.4.id', $oldApproved->id));
});

it('shares only the fields displayed in the list', function () {
    $request = AccountRequest::factory()->create([
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => 'Je suis le cousin de Marie.',
        'created_at' => '2026-11-15 10:30:00',
    ]);
    actingAs(User::factory()->admin()->create());

    get(route('admin.account-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accountRequests.0', [
                'id' => $request->id,
                'name' => 'Paul Martin',
                'email' => 'paul@example.com',
                'message' => 'Je suis le cousin de Marie.',
                'status' => 'pending',
                'createdAt' => $request->created_at?->toIso8601String(),
            ]));
});

it('renders an empty list when there is no request', function () {
    actingAs(User::factory()->admin()->create());

    get(route('admin.account-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('accountRequests', 0));
});

it('brings a signed-out admin back to the requests page after signing in', function () {
    $admin = User::factory()->admin()->create(['password' => 'secret-password']);

    get(route('admin.account-requests.index'))->assertRedirect(route('login'));

    post(route('login.store'), ['email' => $admin->email, 'password' => 'secret-password'])
        ->assertRedirect(route('admin.account-requests.index'));
});
