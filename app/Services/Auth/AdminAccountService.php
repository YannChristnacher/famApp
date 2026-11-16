<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Models\User;

class AdminAccountService
{
    /**
     * Creates the administrator account, or promotes the existing account with
     * this email without changing its name or password.
     */
    public function ensureAdmin(string $name, string $email, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->fill(['name' => $name, 'password' => $password]);
        }

        $user->role = Role::Admin;
        $user->save();

        return $user;
    }
}
