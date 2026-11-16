<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvitationService
{
    /**
     * Saves the first password chosen by an invited member. The account is
     * read again under a lock so the same link cannot be used twice at once.
     *
     * @return bool false when the password had already been set
     */
    public function setFirstPassword(User $user, string $password): bool
    {
        return DB::transaction(function () use ($user, $password): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($locked->hasSetPassword()) {
                return false;
            }

            $locked->forceFill([
                'password' => $password,
                'password_set_at' => now(),
            ])->save();

            return true;
        });
    }
}
