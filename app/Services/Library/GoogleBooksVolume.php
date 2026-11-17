<?php

namespace App\Services\Library;

/**
 * A volume of Google Books, read from an answer of the API by
 * GoogleBooksService: what prefills the book form, and the raw volume kept in
 * books.google_books_data.
 */
final readonly class GoogleBooksVolume
{
    /**
     * @param  string  $author  The authors joined by ", ", empty when Google lists none.
     * @param  string|null  $coverUrl  https URL on an allowed host (GoogleBooksCoverPolicy), null without cover.
     * @param  array<string, mixed>  $raw  The volume as Google sent it.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $author,
        public ?string $coverUrl,
        public array $raw,
    ) {}
}
