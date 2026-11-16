<?php

namespace App\Services\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Str;

class AdminAccountService
{
    /**
     * Creates the administrator account, or promotes the existing account with
     * this email (stored in lower case) without changing its name or password.
     */
    public function ensureAdmin(string $name, string $email, string $password): User
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first()
            ?? new User(['name' => $name, 'email' => $email, 'password' => $password]);

        $user->role = Role::Admin;
        $user->save();

        return $user;
    }
}
