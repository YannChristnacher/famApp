<?php

namespace App\Services\Library;

use App\Models\Library\Book;

/**
 * A book just created or updated by BookService.
 */
final readonly class SavedBook
{
    /**
     * @param  bool  $googleCoverMissing  The Google cover was asked for but could not be downloaded.
     */
    public function __construct(
        public Book $book,
        public bool $googleCoverMissing = false,
    ) {}
}
