<?php

namespace App\Listeners\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Keeps the time of the last sign-in of a member (admin control page). Only
 * the time is stored: no IP address nor user agent.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
