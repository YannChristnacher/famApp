<?php

namespace App\Services\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Exceptions\Library\GoogleBooksUnavailableException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Calls to the Google Books API, from the server only; each call is written
 * to the journal. A found volume stays a short time in the server cache, so
 * that saving the book does not call Google again: the browser only sends the
 * identifier of the volume, never its data.
 */
class GoogleBooksService
{
    public const VOLUME_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public const UNTRUSTED_VOLUME = 'Les informations Google Books n’ont pas pu être vérifiées. Réessayez, ou fermez l’encart Google Books pour enregistrer votre saisie seule.';

    private const TEXT_MAX_LENGTH = 255;

    private const CACHE_PREFIX = 'google-books:volume:';

    public function __construct(
        private readonly GoogleBooksJournal $journal,
        private readonly GoogleBooksCoverPolicy $covers,
    ) {}

    /**
     * The first volume Google lists for the ISBN (normalized), null if none.
     *
     * @throws GoogleBooksUnavailableException
     */
    public function findByIsbn(string $isbn, User $user): ?GoogleBooksVolume
    {
        $volume = $this->journaled(
            GoogleBooksRequestType::Isbn,
            $isbn,
            $user,
            fn (PendingRequest $http) => $http->get('volumes', $this->withKey(['q' => "isbn:{$isbn}"])),
            fn (Response $response) => $this->firstVolume($response),
        );

        if ($volume !== null) {
            $this->remember($volume);
        }

        return $volume;
    }

    /**
     * The volume as Google knows it, from the server cache or from a new
     * call; never from data sent by the browser.
     *
     * @throws ValidationException When Google cannot confirm the volume.
     */
    public function trustedVolume(string $volumeId, User $user): GoogleBooksVolume
    {
        if (preg_match(self::VOLUME_ID_PATTERN, $volumeId) !== 1) {
            throw $this->untrustedVolume();
        }

        $cached = Cache::get(self::CACHE_PREFIX.$volumeId);
        if (is_array($cached)) {
            return $this->volumeFrom($cached);
        }

        try {
            $volume = $this->journaled(
                GoogleBooksRequestType::Volume,
                $volumeId,
                $user,
                fn (PendingRequest $http) => $http->get('volumes/'.rawurlencode($volumeId), $this->withKey([])),
                fn (Response $response) => $this->sameVolume($response, $volumeId),
            );
        } catch (GoogleBooksUnavailableException) {
            throw $this->untrustedVolume();
        }

        $this->remember($volume);

        return $volume;
    }

    /**
     * Sends the request, reads the answer and writes the outcome to the journal.
     *
     * @template T of GoogleBooksVolume|null
     *
     * @param  callable(PendingRequest): Response  $send
     * @param  callable(Response): T  $read  Throws GoogleBooksUnavailableException on an unexpected answer.
     * @return T
     *
     * @throws GoogleBooksUnavailableException
     */
    private function journaled(GoogleBooksRequestType $type, string $query, User $user, callable $send, callable $read): mixed
    {
        $startedAt = hrtime(true);

        try {
            $response = $send($this->client());
        } catch (ConnectionException) {
            $this->journal->record($type, $query, GoogleBooksRequestStatus::Timeout, null, $startedAt, $user);

            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Timeout);
        }

        try {
            if ($response->failed()) {
                throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::fromFailedHttpStatus($response->status()), $response->status());
            }
            $result = $read($response);
        } catch (GoogleBooksUnavailableException $exception) {
            $this->journal->record($type, $query, $exception->status, $response->status(), $startedAt, $user);

            throw $exception;
        }

        $status = $result === null ? GoogleBooksRequestStatus::NotFound : GoogleBooksRequestStatus::Found;
        $this->journal->record($type, $query, $status, $response->status(), $startedAt, $user);

        return $result;
    }

    private function client(): PendingRequest
    {
        $timeout = (int) config('services.google_books.timeout');

        return Http::baseUrl((string) config('services.google_books.base_url'))
            ->timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->acceptJson();
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, string>
     */
    private function withKey(array $query): array
    {
        $key = config('services.google_books.key');

        return is_string($key) && $key !== '' ? [...$query, 'key' => $key] : $query;
    }

    private function firstVolume(Response $response): ?GoogleBooksVolume
    {
        $answer = $response->json();
        if (! is_array($answer)) {
            throw $this->invalidResponse();
        }

        $items = $answer['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            throw $this->invalidResponse();
        }

        return $items === [] ? null : $this->volumeFrom($items[0]);
    }

    private function sameVolume(Response $response, string $volumeId): GoogleBooksVolume
    {
        $volume = $this->volumeFrom($response->json());
        if ($volume->id !== $volumeId) {
            throw $this->invalidResponse();
        }

        return $volume;
    }

    private function volumeFrom(mixed $raw): GoogleBooksVolume
    {
        if (! is_array($raw) || ! is_string($raw['id'] ?? null) || preg_match(self::VOLUME_ID_PATTERN, $raw['id']) !== 1) {
            throw $this->invalidResponse();
        }

        $info = $raw['volumeInfo'] ?? null;
        $title = is_array($info) ? $this->text($info['title'] ?? null) : '';
        if (! is_array($info) || $title === '') {
            throw $this->invalidResponse();
        }

        /** @var array<string, mixed> $raw */
        return new GoogleBooksVolume(
            id: $raw['id'],
            title: $title,
            author: $this->authors($info['authors'] ?? null),
            coverUrl: $this->covers->largestCover($info['imageLinks'] ?? null),
            raw: $raw,
        );
    }

    /**
     * The authors joined in one field (a book has one author field).
     */
    private function authors(mixed $authors): string
    {
        if (! is_array($authors)) {
            return '';
        }

        $names = array_filter(array_map($this->text(...), $authors), fn (string $name) => $name !== '');

        return $this->text(implode(', ', $names));
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, self::TEXT_MAX_LENGTH) : '';
    }

    private function remember(GoogleBooksVolume $volume): void
    {
        Cache::put(
            self::CACHE_PREFIX.$volume->id,
            $volume->raw,
            now()->addMinutes((int) config('services.google_books.cache_minutes')),
        );
    }

    private function invalidResponse(): GoogleBooksUnavailableException
    {
        return new GoogleBooksUnavailableException(GoogleBooksRequestStatus::InvalidResponse);
    }

    private function untrustedVolume(): ValidationException
    {
        return ValidationException::withMessages(['google_volume_id' => self::UNTRUSTED_VOLUME]);
    }
}
