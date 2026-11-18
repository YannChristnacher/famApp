<?php

namespace App\Enums\Library;

/**
 * What a call to Google Books was for (journal of the calls).
 */
enum GoogleBooksRequestType: string
{
    /** Search of the volumes of an ISBN. */
    case Isbn = 'isbn';

    /** Fetch of one volume by its identifier, before saving a book. */
    case Volume = 'volume';

    /** Download of a cover image. */
    case Cover = 'cover';

    /**
     * French label, for the admin control page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Isbn => 'ISBN',
            self::Volume => 'Volume',
            self::Cover => 'Couverture',
        };
    }
}
