<?php

namespace App\Services\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Exceptions\Library\GoogleBooksUnavailableException;
use App\Http\Requests\Library\BookRequest;
use App\Models\User;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Downloads the cover of a trusted Google Books volume and stores it through
 * BookCoverService (resized, re-encoded, random name). Against SSRF, every URL
 * (the first one and each redirect) must pass GoogleBooksCoverPolicy before
 * any request, and the body is bounded in size. A failure is not an error for
 * the member: the book is simply saved without this cover.
 */
class GoogleBooksCoverService
{
    public const MAX_REDIRECTS = 3;

    private const IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public function __construct(
        private readonly GoogleBooksCoverPolicy $policy,
        private readonly BookCoverService $covers,
        private readonly GoogleBooksJournal $journal,
        private readonly GoogleBooksSaveLimiter $limiter,
    ) {}

    /**
     * Path of the stored cover on the public disk, null without cover or when
     * it could not be downloaded.
     */
    public function store(GoogleBooksVolume $volume, User $user): ?string
    {
        if ($volume->coverUrl === null) {
            return null;
        }

        $startedAt = hrtime(true);
        $query = $this->journalQuery($volume->coverUrl);

        try {
            if (! $this->limiter->attempt($user)) {
                throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::RateLimited);
            }
            $image = $this->download($volume->coverUrl);
            $path = $this->storeImage($image);
        } catch (GoogleBooksUnavailableException $exception) {
            $this->journal->record(GoogleBooksRequestType::Cover, $query, $exception->status, $exception->httpStatus, $startedAt, $user);

            return null;
        }

        $this->journal->record(GoogleBooksRequestType::Cover, $query, GoogleBooksRequestStatus::Found, $image->status(), $startedAt, $user);

        return $path;
    }

    /**
     * Follows at most MAX_REDIRECTS redirects by hand, each checked by the policy.
     *
     * @throws GoogleBooksUnavailableException
     */
    private function download(string $url): Response
    {
        $redirectStatus = null;

        for ($redirects = 0; ; $redirects++) {
            if (! $this->policy->allows($url)) {
                throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Refused, $redirectStatus);
            }

            $response = $this->get($url);

            if (! $response->redirect()) {
                return $this->checkedImage($response);
            }

            if ($redirects >= self::MAX_REDIRECTS) {
                throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Refused, $response->status());
            }

            $redirectStatus = $response->status();
            $url = $response->header('Location');
        }
    }

    /**
     * @throws GoogleBooksUnavailableException
     */
    private function get(string $url): Response
    {
        $maxBytes = $this->maxBytes();
        $tooBig = false;
        $timeout = (int) config('services.google_books.cover_timeout');

        try {
            // Raw bytes only (identity, no decoding): the size limit must bound
            // what is really stored, not a compressed body inflated afterwards.
            return Http::withoutRedirecting()
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->timeout($timeout)
                ->connectTimeout(min(3, $timeout))
                ->withOptions([
                    'decode_content' => false,
                    // Stops a body larger than announced (or not announced) while it downloads.
                    'progress' => function (int $total, int $downloaded) use ($maxBytes, &$tooBig): void {
                        if ($total > $maxBytes || $downloaded > $maxBytes) {
                            $tooBig = true;

                            throw new RuntimeException('Cover too big.');
                        }
                    },
                ])
                ->get($url);
        } catch (ConnectionException|TransferException) {
            throw new GoogleBooksUnavailableException($tooBig ? GoogleBooksRequestStatus::Refused : GoogleBooksRequestStatus::Timeout);
        }
    }

    /**
     * @throws GoogleBooksUnavailableException
     */
    private function checkedImage(Response $response): Response
    {
        if ($response->failed()) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::fromFailedHttpStatus($response->status()), $response->status());
        }

        $announced = $response->header('Content-Length');
        if ((is_numeric($announced) && (int) $announced > $this->maxBytes()) || strlen($response->body()) > $this->maxBytes()) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Refused, $response->status());
        }

        $size = @getimagesizefromstring($response->body());
        if ($size === false || ! in_array($size[2], self::IMAGE_TYPES, true)) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::InvalidResponse, $response->status());
        }

        // Same bound as an upload: GD would need too much memory beyond it.
        if (max($size[0], $size[1]) > BookRequest::COVER_MAX_PIXELS_PER_SIDE) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Refused, $response->status());
        }

        return $response;
    }

    /**
     * @throws GoogleBooksUnavailableException
     */
    private function storeImage(Response $image): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'google-cover-');
        if ($temporary === false || file_put_contents($temporary, $image->body()) === false) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::StorageFailed, $image->status());
        }

        try {
            return $this->covers->store(new UploadedFile($temporary, 'cover', null, null, true));
        } catch (ValidationException) {
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::InvalidResponse, $image->status());
        } catch (Throwable) {
            // Disk full, unwritable storage…: the book is saved without this cover.
            throw new GoogleBooksUnavailableException(GoogleBooksRequestStatus::StorageFailed, $image->status());
        } finally {
            @unlink($temporary);
        }
    }

    private function maxBytes(): int
    {
        return (int) config('services.google_books.cover_max_kilobytes') * 1024;
    }

    /**
     * Host and path of the image: its query string is of no use in the journal.
     */
    private function journalQuery(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($host) ? $host.(is_string($path) ? $path : '') : $url;
    }
}
