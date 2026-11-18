<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;

/**
 * An error of the Laravel log as the admin control page shows it: the
 * message only (no context, no trace), masked and truncated.
 */
final readonly class ErrorLogEntry
{
    public function __construct(
        public string $level,
        public string $message,
        public Carbon $loggedAt,
    ) {}
}
