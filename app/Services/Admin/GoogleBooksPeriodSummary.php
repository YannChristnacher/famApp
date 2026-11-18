<?php

namespace App\Services\Admin;

/**
 * Calls to Google Books over a period: number by status, total and average
 * duration (null without any call).
 */
final readonly class GoogleBooksPeriodSummary
{
    /**
     * @param  list<array{status: string, label: string, count: int}>  $statuses
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $total,
        public ?int $averageDurationMs,
        public array $statuses,
    ) {}
}
