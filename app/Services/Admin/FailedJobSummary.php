<?php

namespace App\Services\Admin;

use Illuminate\Support\Carbon;

/**
 * What the admin control page shows of a failed job: never its payload, only
 * the class name read from it, and the first line of the exception, masked
 * and truncated.
 */
final readonly class FailedJobSummary
{
    public function __construct(
        public string $uuid,
        public string $connection,
        public string $queue,
        public string $job,
        public string $exception,
        public Carbon $failedAt,
    ) {}
}
