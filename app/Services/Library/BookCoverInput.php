<?php

namespace App\Services\Library;

use Illuminate\Http\UploadedFile;

/**
 * What the member asked for the cover of a book they save: an uploaded file
 * (which always wins), the removal of the current cover, or the cover of the
 * Google Books volume, downloaded by the server.
 */
final readonly class BookCoverInput
{
    public function __construct(
        public ?UploadedFile $file = null,
        public bool $remove = false,
        public bool $fromGoogle = false,
    ) {}
}
