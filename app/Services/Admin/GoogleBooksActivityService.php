<?php

namespace App\Services\Admin;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Models\Library\GoogleBooksRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Activity of the calls to Google Books (journal google_books_requests) for
 * the admin control page.
 */
class GoogleBooksActivityService
{
    /**
     * Last 24 hours, then last 7 days.
     *
     * @return list<GoogleBooksPeriodSummary>
     */
    public function periods(): array
    {
        return [
            $this->period('day', '24 dernières heures', now()->subDay()),
            $this->period('week', '7 derniers jours', now()->subDays(7)),
        ];
    }

    /**
     * The latest calls that failed (neither found nor not found), newest first.
     *
     * @return Collection<int, GoogleBooksRequest>
     */
    public function latestFailures(int $limit): Collection
    {
        return GoogleBooksRequest::query()
            ->whereNotIn('status', GoogleBooksRequestStatus::answered())
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function period(string $key, string $label, Carbon $since): GoogleBooksPeriodSummary
    {
        // Fixed aggregate expressions, no input: one grouped query per period.
        $rows = GoogleBooksRequest::query()
            ->toBase()
            ->selectRaw('status, count(*) as calls, sum(duration_ms) as total_duration')
            ->where('created_at', '>=', $since)
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [];
        $total = 0;
        $duration = 0;

        foreach (GoogleBooksRequestStatus::cases() as $status) {
            $row = $rows->get($status->value);

            if ($row === null) {
                continue;
            }

            $calls = (int) $row->calls;
            $total += $calls;
            $duration += (int) $row->total_duration;
            $statuses[] = ['status' => $status->value, 'label' => $status->label(), 'count' => $calls];
        }

        return new GoogleBooksPeriodSummary(
            key: $key,
            label: $label,
            total: $total,
            averageDurationMs: $total === 0 ? null : (int) round($duration / $total),
            statuses: $statuses,
        );
    }
}
