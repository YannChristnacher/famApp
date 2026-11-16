<?php

use App\Enums\AccountRequestStatus;
use App\Jobs\Auth\SubmitAccountRequest;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\AccountRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * @return array{name: string, email: string, message: string|null}
 */
function submittedAccountRequest(string $email = 'paul@example.com'): array
{
    return ['name' => 'Paul Martin', 'email' => $email, 'message' => 'Je suis le cousin de Marie.'];
}

it('is queued', function () {
    expect(new SubmitAccountRequest(submittedAccountRequest()))->toBeInstanceOf(ShouldQueue::class);
});

it('stores a new request and notifies every administrator, and no other user', function () {
    Notification::fake();
    $admins = User::factory()->admin()->count(2)->create();
    $user = User::factory()->create();

    SubmitAccountRequest::dispatchSync(submittedAccountRequest());

    $request = AccountRequest::query()->sole();
    expect($request->email)->toBe('paul@example.com')
        ->and($request->status)->toBe(AccountRequestStatus::Pending);
    Notification::assertSentTo($admins, AccountRequestedNotification::class);
    Notification::assertNotSentTo($user, AccountRequestedNotification::class);
});

it('ignores a request for an email that already has an account', function () {
    Notification::fake();
    User::factory()->admin()->create();
    User::factory()->create(['email' => 'paul@example.com']);

    SubmitAccountRequest::dispatchSync(submittedAccountRequest());

    expect(AccountRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('ignores a request for an email that already has a pending request', function () {
    Notification::fake();
    User::factory()->admin()->create();
    AccountRequest::factory()->create(['email' => 'paul@example.com', 'message' => 'Première demande']);

    SubmitAccountRequest::dispatchSync(submittedAccountRequest());

    expect(AccountRequest::query()->sole()->message)->toBe('Première demande');
    Notification::assertNothingSent();
});
