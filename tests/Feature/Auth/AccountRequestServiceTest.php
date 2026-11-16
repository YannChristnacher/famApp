<?php

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use App\Services\Auth\AccountRequestService;
use Illuminate\Support\Facades\Notification;

it('stores the request even when there is no administrator to notify', function () {
    Notification::fake();

    app(AccountRequestService::class)->submit([
        'name' => 'Paul Martin',
        'email' => 'paul@example.com',
        'message' => null,
    ]);

    expect(AccountRequest::query()->sole()->status)->toBe(AccountRequestStatus::Pending);
    Notification::assertNothingSent();
});

it('does not let the status be mass assigned', function () {
    $request = new AccountRequest(['name' => 'Paul', 'email' => 'paul@example.com', 'status' => 'approved']);

    expect($request->status)->toBe(AccountRequestStatus::Pending);
});
