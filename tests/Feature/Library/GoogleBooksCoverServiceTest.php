<?php

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use App\Services\Library\BookCoverService;
use App\Services\Library\GoogleBooksCoverService;
use App\Services\Library\GoogleBooksVolume;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Http::preventStrayRequests();
    config([
        'services.google_books.cover_hosts' => ['books.google.com', 'books.googleusercontent.com'],
        'services.google_books.cover_max_kilobytes' => 2048,
    ]);
});

function volumeWithCover(?string $coverUrl): GoogleBooksVolume
{
    return new GoogleBooksVolume('zyTCAlFPjgYC', 'Le Hobbit', 'J. R. R. Tolkien', $coverUrl, googleVolume());
}

function storeGoogleCover(?string $coverUrl, ?User $user = null): ?string
{
    return app(GoogleBooksCoverService::class)->store(volumeWithCover($coverUrl), $user ?? User::factory()->create());
}

const GOOGLE_COVER_URL = 'https://books.google.com/books/content?id=zyTCAlFPjgYC&printsec=frontcover&img=1&zoom=1';

it('downloads the cover and stores it resized, under a random name', function () {
    Http::fake(['books.google.com/*' => Http::response(jpegBytes(1280, 1920), 200, ['Content-Type' => 'image/jpeg'])]);
    $user = User::factory()->create();

    $path = storeGoogleCover(GOOGLE_COVER_URL, $user);

    expect($path)->toStartWith('covers/')->toEndWith('.jpg')
        ->and(str_contains((string) $path, 'zyTCAlFPjgYC'))->toBeFalse()
        ->and(storedImage((string) $path))->toBe([800, 1200, 'mime' => 'image/jpeg'])
        ->and(Storage::disk('public')->allFiles())->toBe([$path]);
    Http::assertSent(fn (Request $request) => $request->url() === GOOGLE_COVER_URL);

    $log = GoogleBooksRequest::query()->sole();
    expect($log->type)->toBe(GoogleBooksRequestType::Cover)
        ->and($log->query)->toBe('books.google.com/books/content')
        ->and($log->status)->toBe(GoogleBooksRequestStatus::Found)
        ->and($log->http_status)->toBe(200)
        ->and($log->user_id)->toBe($user->id);
});

it('downloads the raw bytes, never a decompressed body', function () {
    $sent = [];
    Http::fake(function (Request $request, array $options) use (&$sent) {
        $sent = ['options' => $options, 'encoding' => $request->header('Accept-Encoding')];

        return Http::response(jpegBytes(300, 450), 200, ['Content-Type' => 'image/jpeg']);
    });

    expect(storeGoogleCover(GOOGLE_COVER_URL))->not->toBeNull()
        ->and($sent['options']['decode_content'] ?? null)->toBeFalse()
        ->and($sent['encoding'])->toBe(['identity']);
});

it('refuses a compressed body instead of inflating it', function () {
    // 10 Mo of zeros gzipped into a few kilobytes: kept compressed, it is not an image.
    $bomb = (string) gzencode(str_repeat("\0", 10 * 1024 * 1024), 9);
    Http::fake(['books.google.com/*' => Http::response($bomb, 200, ['Content-Type' => 'image/jpeg', 'Content-Encoding' => 'gzip'])]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe(GoogleBooksRequestStatus::InvalidResponse);
});

it('journals a cover that cannot be written and gives up', function () {
    Http::fake(['books.google.com/*' => Http::response(jpegBytes(300, 450), 200, ['Content-Type' => 'image/jpeg'])]);
    app()->instance(BookCoverService::class, Mockery::mock(BookCoverService::class)
        ->shouldReceive('store')->andThrow(new RuntimeException('No space left on device'))->getMock());

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull();

    $log = GoogleBooksRequest::query()->sole();
    expect($log->status)->toBe(GoogleBooksRequestStatus::StorageFailed)
        ->and($log->http_status)->toBe(200);
});

it('limits the cover downloads of each member', function () {
    config(['services.google_books.save_calls_per_minute' => 1]);
    Http::fake(['books.google.com/*' => Http::response(jpegBytes(300, 450), 200, ['Content-Type' => 'image/jpeg'])]);
    $user = User::factory()->create();

    expect(storeGoogleCover(GOOGLE_COVER_URL, $user))->not->toBeNull()
        ->and(storeGoogleCover(GOOGLE_COVER_URL, $user))->toBeNull()
        ->and(storeGoogleCover(GOOGLE_COVER_URL))->not->toBeNull();

    Http::assertSentCount(2);
    expect(GoogleBooksRequest::query()->where('user_id', $user->id)->pluck('status')->all())
        ->toBe([GoogleBooksRequestStatus::Found, GoogleBooksRequestStatus::RateLimited]);
});

it('stores nothing for a volume without cover, without any call', function () {
    Http::fake();

    expect(storeGoogleCover(null))->toBeNull();

    Http::assertNothingSent();
    expect(GoogleBooksRequest::query()->count())->toBe(0);
});

it('refuses a cover URL that is not https on an allowed host, without any call', function (string $url) {
    Http::fake();

    expect(storeGoogleCover($url))->toBeNull();

    Http::assertNothingSent();
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe(GoogleBooksRequestStatus::Refused);
})->with([
    'http' => ['http://books.google.com/books/content?id=x'],
    'unknown host' => ['https://evil.example.com/cover.jpg'],
    'look-alike host' => ['https://books.google.com.evil.example/cover.jpg'],
    'internal address' => ['https://169.254.169.254/latest/meta-data'],
    'other scheme' => ['file:///etc/passwd'],
    'credentials' => ['https://attacker@books.google.com/cover.jpg'],
    'other port' => ['https://books.google.com:8443/cover.jpg'],
    'not a URL' => ['books.google.com/cover.jpg'],
]);

it('follows a redirect to an allowed host only', function () {
    Http::fake([
        'books.google.com/*' => Http::response('', 302, ['Location' => 'https://books.googleusercontent.com/img/1.jpg']),
        'books.googleusercontent.com/*' => Http::response(jpegBytes(300, 450), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    expect(storedImage((string) storeGoogleCover(GOOGLE_COVER_URL)))->toBe([300, 450, 'mime' => 'image/jpeg']);
    Http::assertSentCount(2);
});

it('refuses a redirect to another host or to http, without calling it', function (string $location) {
    Http::fake([
        'books.google.com/*' => Http::response('', 302, ['Location' => $location]),
    ]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull();

    Http::assertSentCount(1);
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe(GoogleBooksRequestStatus::Refused);
})->with([
    'other host' => ['https://evil.example.com/cover.jpg'],
    'http' => ['http://books.googleusercontent.com/cover.jpg'],
    'internal address' => ['http://127.0.0.1/admin'],
    'no location' => [''],
]);

it('stops after too many redirects', function () {
    Http::fake([
        'books.google.com/*' => Http::response('', 302, ['Location' => GOOGLE_COVER_URL]),
    ]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull();

    Http::assertSentCount(4);
});

it('refuses a cover too big, announced or not', function (array $headers) {
    config(['services.google_books.cover_max_kilobytes' => 1]);
    Http::fake(['books.google.com/*' => Http::response(jpegBytes(400, 600).str_repeat('0', 2048), 200, $headers)]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe(GoogleBooksRequestStatus::Refused);
})->with([
    'announced' => [['Content-Type' => 'image/jpeg', 'Content-Length' => '999999']],
    'not announced' => [['Content-Type' => 'image/jpeg']],
]);

it('refuses a file that is not a JPEG, PNG or WebP image', function (string $body) {
    Http::fake(['books.google.com/*' => Http::response($body, 200, ['Content-Type' => 'image/jpeg'])]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe(GoogleBooksRequestStatus::InvalidResponse);
})->with([
    'html' => ['<html><body>not found</body></html>'],
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>'],
    'gif' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')],
]);

it('refuses an image wider than the upload limit', function () {
    // A PNG header announcing 7000×10 pixels is enough: it must be refused before GD decodes it.
    $png = transparentPng(7000, 10)->getContent();
    Http::fake(['books.google.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('gives up on a failing or slow image server', function (mixed $response, GoogleBooksRequestStatus $status) {
    Http::fake(['books.google.com/*' => $response]);

    expect(storeGoogleCover(GOOGLE_COVER_URL))->toBeNull()
        ->and(GoogleBooksRequest::query()->sole()->status)->toBe($status);
})->with([
    'not found' => [fn () => Http::response('', 404), GoogleBooksRequestStatus::NotFound],
    'forbidden' => [fn () => Http::response('', 403), GoogleBooksRequestStatus::ClientError],
    'server error' => [fn () => Http::response('', 500), GoogleBooksRequestStatus::ServerError],
    'timeout' => [fn () => Http::failedConnection(), GoogleBooksRequestStatus::Timeout],
]);
