<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * Roles an administrator may give when creating an account.
     *
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return [self::User->value, self::Admin->value];
    }
}
