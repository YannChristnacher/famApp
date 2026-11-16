<?php

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    Notification::fake();
    actingAs(User::factory()->admin()->create());
});

it('rejects a pending request without creating an account or sending an email', function () {
    $request = AccountRequest::factory()->create(['name' => 'Paul Martin', 'email' => 'paul@example.com']);
    $userCount = User::query()->count();

    post(route('admin.account-requests.reject', $request))
        ->assertRedirect(route('admin.account-requests.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'La demande de Paul Martin a été refusée.']);

    expect($request->fresh()?->status)->toBe(AccountRequestStatus::Rejected)
        ->and($request->fresh()?->user_id)->toBeNull()
        ->and(User::query()->count())->toBe($userCount);
    Notification::assertNothingSent();
});

it('refuses to reject a request already processed', function (string $state) {
    $request = AccountRequest::factory()->{$state}()->create();

    post(route('admin.account-requests.reject', $request))
        ->assertRedirect(route('admin.account-requests.index'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Cette demande a déjà été traitée.']);

    expect($request->fresh()?->status)->toBe(AccountRequestStatus::from($state));
    Notification::assertNothingSent();
})->with(['approved', 'rejected']);
