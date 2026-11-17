<?php

namespace App\Enums\Library;

/**
 * Outcome of a call to Google Books (journal of the calls).
 */
enum GoogleBooksRequestStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case RateLimited = 'rate_limited';
    case ClientError = 'client_error';
    case ServerError = 'server_error';
    case Timeout = 'timeout';
    case InvalidResponse = 'invalid_response';

    /** Cover URL or redirect refused before any request (not https, host not allowed, too big). */
    case Refused = 'refused';

    /**
     * Status of an HTTP answer that is not a success.
     */
    public static function fromFailedHttpStatus(int $httpStatus): self
    {
        return match (true) {
            $httpStatus === 404 => self::NotFound,
            $httpStatus === 429 => self::RateLimited,
            $httpStatus >= 500 => self::ServerError,
            default => self::ClientError,
        };
    }
}
