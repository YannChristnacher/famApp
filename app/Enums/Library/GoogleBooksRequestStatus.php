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

    /** The downloaded cover could not be written (disk full, unreadable image…). */
    case StorageFailed = 'storage_failed';

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

    /**
     * French label, for the admin control page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Found => 'Trouvé',
            self::NotFound => 'Introuvable',
            self::RateLimited => 'Quota dépassé',
            self::ClientError => 'Erreur de requête',
            self::ServerError => 'Erreur de Google',
            self::Timeout => 'Délai dépassé',
            self::InvalidResponse => 'Réponse invalide',
            self::StorageFailed => 'Enregistrement impossible',
            self::Refused => 'Refusé',
        };
    }

    /**
     * Statuses that are not a failure: the call got an answer.
     *
     * @return list<self>
     */
    public static function answered(): array
    {
        return [self::Found, self::NotFound];
    }
}
