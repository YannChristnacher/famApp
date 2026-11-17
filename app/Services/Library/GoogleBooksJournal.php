<?php

namespace App\Services\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;

/**
 * Writes one line per call to Google Books (table google_books_requests),
 * for the admin control page.
 */
class GoogleBooksJournal
{
    private const QUERY_MAX_LENGTH = 255;

    /**
     * @param  int  $startedAt  hrtime(true) taken just before the call.
     */
    public function record(
        GoogleBooksRequestType $type,
        string $query,
        GoogleBooksRequestStatus $status,
        ?int $httpStatus,
        int $startedAt,
        User $user,
    ): void {
        $entry = new GoogleBooksRequest([
            'type' => $type,
            'query' => mb_substr($query, 0, self::QUERY_MAX_LENGTH),
            'status' => $status,
            'http_status' => $httpStatus,
            'duration_ms' => intdiv(max(0, hrtime(true) - $startedAt), 1_000_000),
        ]);
        $entry->user()->associate($user);
        $entry->save();
    }
}
