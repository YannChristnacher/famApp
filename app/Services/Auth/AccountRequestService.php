<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\AccountRequestedNotification;
use Illuminate\Support\Facades\Notification;

class AccountRequestService
{
    /**
     * Records the request and tells the administrators about it. A request for
     * an email that already has an account or a pending request is silently
     * ignored: the visitor gets the same answer either way, so nobody can find
     * out which emails are known, and the administrators are not flooded.
     *
     * @param  array{name: string, email: string, message?: string|null}  $data
     */
    public function submit(array $data): void
    {
        if ($this->isAlreadyKnown($data['email'])) {
            return;
        }

        $accountRequest = AccountRequest::query()->create($data);

        Notification::send(
            User::query()->where('role', Role::Admin)->get(),
            new AccountRequestedNotification($accountRequest),
        );
    }

    private function isAlreadyKnown(string $email): bool
    {
        return User::query()->where('email', $email)->exists()
            || AccountRequest::query()->pending()->where('email', $email)->exists();
    }
}
