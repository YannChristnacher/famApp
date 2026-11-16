<?php

namespace App\Services\Admin;

use App\Enums\AccountRequestStatus;
use App\Enums\Role;
use App\Exceptions\Admin\AccountRequestAlreadyProcessedException;
use App\Models\Auth\AccountRequest;
use App\Models\User;
use App\Notifications\Auth\SetPasswordNotification;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AccountRequestReviewService
{
    public function __construct(private readonly PasswordBroker $passwords) {}

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
     * request and emails the new member a link to choose their password.
     *
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function createAccount(AccountRequest $request, array $data): User
    {
        return DB::transaction(function () use ($request, $data): User {
            $request = $this->lockPending($request);

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
            ]);
            $user->role = Role::from($data['role']);
            $user->save();

            $request->status = AccountRequestStatus::Approved;
            $request->user()->associate($user);
            $request->save();

            $this->sendSetPasswordLink($user);

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

    private function sendSetPasswordLink(User $user): void
    {
        $status = $this->passwords->sendResetLink(
            ['email' => $user->email],
            fn (User $user, string $token) => $user->notify(new SetPasswordNotification($token)),
        );

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            throw new RuntimeException("The set password link could not be created ({$status}).");
        }
    }
}
