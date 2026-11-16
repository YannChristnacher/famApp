<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Chooses the Ziggy route groups (config/ziggy.php) printed in the page: the
 * admin routes are only ever sent to administrators.
 */
class ZiggyGroup
{
    /**
     * @return list<string>
     */
    public static function for(?Authenticatable $user): array
    {
        if ($user === null) {
            return ['guest'];
        }

        return $user instanceof User && $user->isAdmin() ? ['auth', 'admin'] : ['auth'];
    }
}
