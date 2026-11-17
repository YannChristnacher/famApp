<?php

namespace App\Services\Library;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Budget of the calls to Google Books made while a member saves books
 * (confirmation of a volume no longer cached, download of a cover). The
 * lookup endpoint has its own limiter (throttle:google-books).
 */
class GoogleBooksSaveLimiter
{
    private const DECAY_SECONDS = 60;

    /**
     * Counts one call for the member; false when their budget is spent.
     */
    public function attempt(User $user): bool
    {
        $key = 'google-books-save:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('services.google_books.save_calls_per_minute'))) {
            return false;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return true;
    }
}
