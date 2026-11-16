<?php

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(AccountRequest): Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>>
 */
function adminAccountRequestCalls(): array
{
    return [
        'index' => fn (AccountRequest $request) => get(route('admin.account-requests.index')),
        'account.create' => fn (AccountRequest $request) => get(route('admin.account-requests.account.create', $request)),
        'account.store' => fn (AccountRequest $request) => post(route('admin.account-requests.account.store', $request), [
            'name' => 'Paul Martin',
            'email' => 'paul@example.com',
            'role' => 'user',
        ]),
        'reject' => fn (AccountRequest $request) => post(route('admin.account-requests.reject', $request)),
    ];
}

it('lets an admin reach every account request route', function (string $call) {
    Notification::fake();
    $request = AccountRequest::factory()->create(['email' => 'paul@example.com']);
    actingAs(User::factory()->admin()->create());

    $response = adminAccountRequestCalls()[$call]($request);

    expect($response->getStatusCode())->toBeIn([200, 302])
        ->and($response->headers->get('Location'))->not->toBe(route('login'));
})->with(['index', 'account.create', 'account.store', 'reject']);

it('forbids a regular user every account request route and changes nothing', function (string $call) {
    Notification::fake();
    $request = AccountRequest::factory()->create(['email' => 'paul@example.com']);
    actingAs(User::factory()->create());

    adminAccountRequestCalls()[$call]($request)->assertForbidden();

    expect($request->fresh()?->status)->toBe(AccountRequestStatus::Pending)
        ->and(User::query()->where('email', 'paul@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
})->with(['index', 'account.create', 'account.store', 'reject']);

it('redirects a guest to the login page on every account request route and changes nothing', function (string $call) {
    Notification::fake();
    $request = AccountRequest::factory()->create(['email' => 'paul@example.com']);

    adminAccountRequestCalls()[$call]($request)->assertRedirect(route('login'));

    expect($request->fresh()?->status)->toBe(AccountRequestStatus::Pending)
        ->and(User::query()->where('email', 'paul@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
})->with(['index', 'account.create', 'account.store', 'reject']);
