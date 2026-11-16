<?php

use App\Enums\AccountRequestStatus;
use App\Enums\Role;
use App\Exceptions\Admin\AccountRequestAlreadyProcessedException;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use App\Services\Admin\AccountRequestReviewService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

beforeEach(fn () => Notification::fake());

it('creates the account, approves the request and links it to the user', function () {
    $request = AccountRequest::factory()->create();

    $user = app(AccountRequestReviewService::class)->createAccount($request, [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'admin',
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->role)->toBe(Role::Admin)
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::Approved)
        ->and($request->fresh()?->user?->is($user))->toBeTrue();
    Notification::assertSentTo($user, SetPasswordNotification::class);
});

it('keeps nothing when the account creation fails midway', function () {
    mock(Dispatcher::class, function (MockInterface $notifications) {
        $notifications->shouldReceive('send')->andThrow(new RuntimeException('Panne'));
    });
    $request = AccountRequest::factory()->create();

    expect(fn () => app(AccountRequestReviewService::class)->createAccount($request, [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ]))->toThrow(RuntimeException::class, 'Panne');

    expect(User::query()->where('email', 'paul@example.com')->exists())->toBeFalse()
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::Pending);
});

it('checks the stored status, not the one of a stale model', function () {
    $request = AccountRequest::factory()->create();
    $stale = AccountRequest::query()->findOrFail($request->id);
    app(AccountRequestReviewService::class)->reject($request);

    expect(fn () => app(AccountRequestReviewService::class)->createAccount($stale, [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ]))->toThrow(AccountRequestAlreadyProcessedException::class);

    expect(User::query()->where('email', 'paul@example.com')->exists())->toBeFalse()
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::Rejected);
});

it('refuses to reject a request twice', function () {
    $request = AccountRequest::factory()->approved()->create();

    expect(fn () => app(AccountRequestReviewService::class)->reject($request))
        ->toThrow(AccountRequestAlreadyProcessedException::class);
});

it('sorts the pending requests first, then the most recent ones', function () {
    $a = AccountRequest::factory()->rejected()->create(['created_at' => now()->subMinute()]);
    $b = AccountRequest::factory()->create(['created_at' => now()->subDay()]);
    $c = AccountRequest::factory()->approved()->create(['created_at' => now()->subWeek()]);
    $d = AccountRequest::factory()->create(['created_at' => now()->subHour()]);

    expect(app(AccountRequestReviewService::class)->pendingFirst()->pluck('id')->all())
        ->toBe([$d->id, $b->id, $a->id, $c->id]);
});

it('turns an email taken since the validation into a validation error on the email', function () {
    $request = AccountRequest::factory()->create();
    User::factory()->create(['email' => 'paul@example.com']);
    $userCount = User::query()->count();

    $errors = null;
    try {
        app(AccountRequestReviewService::class)->createAccount($request, [
            'name' => 'Paul Martin',
            'email' => 'paul@example.com',
            'role' => 'user',
        ]);
    } catch (ValidationException $exception) {
        $errors = $exception->errors();
    }

    expect($errors)->toBe(['email' => ['La valeur du champ adresse e-mail est déjà utilisée.']]);

    expect(User::query()->count())->toBe($userCount)
        ->and($request->fresh()?->status)->toBe(AccountRequestStatus::Pending);
    Notification::assertNothingSent();
});

it('creates the account with a password still to be set', function () {
    $user = app(AccountRequestReviewService::class)->createAccount(AccountRequest::factory()->create(), [
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'role' => 'user',
    ]);

    expect($user->fresh()?->password_set_at)->toBeNull();
});
