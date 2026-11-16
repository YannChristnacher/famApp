<?php

namespace App\Services\Admin;

use App\Enums\AccountRequestStatus;
use App\Enums\Role;
use App\Exceptions\Admin\AccountRequestAlreadyProcessedException;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountRequestReviewService
{
    /**
     * Pending requests first, then the most recent ones.
     *
     * @return Collection<int, AccountRequest>
     */
    public function pendingFirst(): Collection
    {
        return AccountRequest::query()
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->sortBy(fn (AccountRequest $request): int => $request->isPending() ? 0 : 1)
            ->values();
    }

    /**
     * Fails when the request is no longer pending, so the administrator does
     * not open the account form for nothing.
     */
    public function ensurePending(AccountRequest $request): void
    {
        if (! $request->isPending()) {
            throw new AccountRequestAlreadyProcessedException;
        }
    }

    /**
     * Creates the account with a random password nobody knows, approves the
     * request and emails the new member a signed link to choose their password.
     *
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function createAccount(AccountRequest $request, array $data): User
    {
        return DB::transaction(function () use ($request, $data): User {
            $request = $this->lockPending($request);

            $user = $this->createInvitedUser($data);

            $request->status = AccountRequestStatus::Approved;
            $request->user()->associate($user);
            $request->save();

            $user->notify(new SetPasswordNotification);

            return $user;
        });
    }

    public function reject(AccountRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            $request = $this->lockPending($request);
            $request->status = AccountRequestStatus::Rejected;
            $request->save();
        });
    }

    /**
     * Reads the stored request again under a lock, so two administrators (or a
     * double click) cannot process the same request twice.
     */
    private function lockPending(AccountRequest $request): AccountRequest
    {
        $locked = AccountRequest::query()->lockForUpdate()->findOrFail($request->getKey());
        $this->ensurePending($locked);

        return $locked;
    }

    /**
     * The password is random and unknown: the member chooses theirs from the
     * invitation link. The email may have been taken since the form was
     * validated (two administrators at once): same error as the validation.
     *
     * @param  array{name: string, email: string, role: string}  $data
     */
    private function createInvitedUser(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(64),
        ]);
        $user->role = Role::from($data['role']);

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => __('validation.attributes.email')]),
            ]);
        }

        return $user;
    }
}
