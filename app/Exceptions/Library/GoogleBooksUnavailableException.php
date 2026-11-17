<?php

namespace App\Exceptions\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use RuntimeException;

/**
 * Google Books refused, failed or answered something unexpected; the member
 * can still type the book by hand.
 */
class GoogleBooksUnavailableException extends RuntimeException
{
    /**
     * @param  int|null  $httpStatus  Status of Google's answer, null when none came.
     */
    public function __construct(
        public readonly GoogleBooksRequestStatus $status,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($status === GoogleBooksRequestStatus::RateLimited
            ? 'Le quota de recherches Google Books est atteint. Réessayez plus tard ou saisissez le livre à la main.'
            : 'Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.');
    }
}
