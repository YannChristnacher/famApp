<?php

namespace App\Http\Requests\Library;

use App\Models\User;
use App\Rules\Library\Isbn;
use App\Services\Library\BookCoverInput;
use App\Services\Library\GoogleBooksService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;

/**
 * Fields of a book, shared by the creation and the edition: title, author,
 * optional ISBN (normalized), bookshelves of the member, optional cover image,
 * and the identifier of the Google Books volume chosen by a lookup (its data is
 * fetched again by the server, never taken from the request).
 */
abstract class BookRequest extends FormRequest
{
    public const TEXT_MAX_LENGTH = 255;

    public const COVER_MAX_KILOBYTES = 10 * 1024;

    /** Beyond this size, decoding the image would take too much memory. */
    public const COVER_MAX_PIXELS_PER_SIDE = 6000;

    /**
     * @return array<string, array<int, string|ValidationRule|Exists|File|Dimensions>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'title' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'author' => ['required', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'isbn' => ['nullable', 'string', new Isbn],
            'bookshelf_ids' => ['array'],
            'bookshelf_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('bookshelves', 'id')->where('user_id', $user->id),
            ],
            'cover' => [
                'nullable',
                File::image()->types(['jpeg', 'png', 'webp'])->max(self::COVER_MAX_KILOBYTES),
                Rule::dimensions()
                    ->maxWidth(self::COVER_MAX_PIXELS_PER_SIDE)
                    ->maxHeight(self::COVER_MAX_PIXELS_PER_SIDE),
            ],
            'google_volume_id' => ['nullable', 'string', 'regex:'.GoogleBooksService::VOLUME_ID_PATTERN],
            'use_google_cover' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bookshelf_ids.*.exists' => 'Une des étagères choisies n’existe pas.',
            'google_volume_id.regex' => 'L’identifiant du volume Google Books est invalide.',
            'cover.dimensions' => 'L’image de couverture ne doit pas dépasser '.self::COVER_MAX_PIXELS_PER_SIDE.' pixels de côté.',
        ];
    }

    /**
     * The validated fields of the book itself (title, author, isbn).
     *
     * @return array{title: string, author: string, isbn: string|null}
     */
    public function bookAttributes(): array
    {
        /** @var array{title: string, author: string, isbn?: string|null} $validated */
        $validated = $this->safe()->only(['title', 'author', 'isbn']);

        return [
            'title' => $validated['title'],
            'author' => $validated['author'],
            'isbn' => $validated['isbn'] ?? null,
        ];
    }

    /**
     * @return list<int>
     */
    public function bookshelfIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('bookshelf_ids', []);

        return array_map(intval(...), $ids);
    }

    public function cover(): ?UploadedFile
    {
        $cover = $this->file('cover');

        return $cover instanceof UploadedFile ? $cover : null;
    }

    public function googleVolumeId(): ?string
    {
        $volumeId = $this->validated('google_volume_id');

        return is_string($volumeId) && $volumeId !== '' ? $volumeId : null;
    }

    /**
     * The Google cover is used only for a Google volume and without uploaded file.
     */
    public function usesGoogleCover(): bool
    {
        return $this->boolean('use_google_cover') && $this->googleVolumeId() !== null && $this->cover() === null;
    }

    public function coverInput(): BookCoverInput
    {
        return new BookCoverInput(file: $this->cover(), fromGoogle: $this->usesGoogleCover());
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'author'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('isbn'))) {
            $isbn = Isbn::normalize($this->input('isbn'));
            $this->merge(['isbn' => $isbn === '' ? null : $isbn]);
        }
    }
}
