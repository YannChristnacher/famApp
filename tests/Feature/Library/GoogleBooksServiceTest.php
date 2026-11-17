<?php

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Exceptions\Library\GoogleBooksUnavailableException;
use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use App\Services\Library\GoogleBooksService;
use App\Services\Library\GoogleBooksVolume;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.google_books.base_url' => 'https://www.googleapis.com/books/v1',
        'services.google_books.key' => null,
    ]);
});

function findByIsbn(string $isbn, ?User $user = null): ?GoogleBooksVolume
{
    return app(GoogleBooksService::class)->findByIsbn($isbn, $user ?? User::factory()->create());
}

/**
 * Status carried by the exception of a failed ISBN lookup.
 */
function failedLookupStatus(): GoogleBooksRequestStatus
{
    try {
        findByIsbn('9782070368228');
    } catch (GoogleBooksUnavailableException $exception) {
        return $exception->status;
    }

    throw new RuntimeException('The lookup did not fail.');
}

it('finds the first volume of an ISBN', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([
        googleVolume('zyTCAlFPjgYC'),
        googleVolume('other', ['title' => 'Autre']),
    ]))]);
    $user = User::factory()->create();

    $volume = findByIsbn('9782070368228', $user);

    expect($volume)->not->toBeNull()
        ->and($volume->id)->toBe('zyTCAlFPjgYC')
        ->and($volume->title)->toBe('Le Hobbit')
        ->and($volume->author)->toBe('J. R. R. Tolkien')
        ->and($volume->coverUrl)->toBe('https://books.google.com/books/content?id=zyTCAlFPjgYC&printsec=frontcover&img=1&zoom=1&source=gbs_api')
        ->and($volume->raw)->toBe(googleVolume('zyTCAlFPjgYC'));

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes?')
        && $request['q'] === 'isbn:9782070368228'
        && ! isset($request['key']));
    Http::assertSentCount(1);

    $log = GoogleBooksRequest::query()->sole();
    expect($log->type)->toBe(GoogleBooksRequestType::Isbn)
        ->and($log->query)->toBe('9782070368228')
        ->and($log->status)->toBe(GoogleBooksRequestStatus::Found)
        ->and($log->http_status)->toBe(200)
        ->and($log->duration_ms)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->created_at)->not->toBeNull();
});

it('sends the API key only when one is configured', function () {
    config(['services.google_books.key' => 'secret-key']);
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([googleVolume()]))]);

    findByIsbn('9782070368228');

    Http::assertSent(fn (Request $request) => $request['key'] === 'secret-key');
});

it('does not send an empty API key', function () {
    config(['services.google_books.key' => '']);
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([googleVolume()]))]);

    findByIsbn('9782070368228');

    Http::assertSent(fn (Request $request) => ! str_contains($request->url(), 'key='));
});

it('joins several authors into one field', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([
        googleVolume(volumeInfo: ['authors' => ['Terry Pratchett', 'Neil Gaiman']]),
    ]))]);

    expect(findByIsbn('9782070368228')?->author)->toBe('Terry Pratchett, Neil Gaiman');
});

it('accepts a volume without authors nor cover', function () {
    $raw = googleVolume();
    unset($raw['volumeInfo']['authors'], $raw['volumeInfo']['imageLinks']);
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([$raw]))]);

    $volume = findByIsbn('9782070368228');

    expect($volume?->author)->toBe('')
        ->and($volume?->coverUrl)->toBeNull();
});

it('picks the largest cover and never keeps a cover of an unknown host', function (array $imageLinks, ?string $expected) {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([
        googleVolume(volumeInfo: ['imageLinks' => $imageLinks]),
    ]))]);

    expect(findByIsbn('9782070368228')?->coverUrl)->toBe($expected);
})->with([
    'largest' => [
        ['thumbnail' => 'http://books.google.com/t.jpg', 'large' => 'https://books.googleusercontent.com/l.jpg'],
        'https://books.googleusercontent.com/l.jpg',
    ],
    'unknown host' => [['thumbnail' => 'http://evil.example.com/books.google.com.jpg'], null],
    'look-alike host' => [['thumbnail' => 'https://books.google.com.evil.example/c.jpg'], null],
    'other scheme' => [['thumbnail' => 'file:///etc/passwd'], null],
    'credentials' => [['thumbnail' => 'https://user@books.google.com/c.jpg'], null],
    'not a string' => [['thumbnail' => ['x']], null],
]);

it('truncates a title or an author too long for the form', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([
        googleVolume(volumeInfo: ['title' => str_repeat('a', 300), 'authors' => [str_repeat('b', 300)]]),
    ]))]);

    $volume = findByIsbn('9782070368228');

    expect(mb_strlen((string) $volume?->title))->toBe(255)
        ->and(mb_strlen((string) $volume?->author))->toBe(255);
});

it('finds nothing for an unknown ISBN', function (array $answer) {
    Http::fake(['www.googleapis.com/*' => Http::response($answer)]);

    expect(findByIsbn('9782070368228'))->toBeNull();

    $log = GoogleBooksRequest::query()->sole();
    expect($log->status)->toBe(GoogleBooksRequestStatus::NotFound)
        ->and($log->http_status)->toBe(200);
})->with([
    'no items' => [googleSearch([])],
    'empty items' => [['kind' => 'books#volumes', 'totalItems' => 0, 'items' => []]],
]);

it('reports a refused, failing or unexpected answer of Google', function (mixed $response, GoogleBooksRequestStatus $status, ?int $httpStatus) {
    Http::fake(['www.googleapis.com/*' => $response]);

    expect(failedLookupStatus())->toBe($status);

    $log = GoogleBooksRequest::query()->sole();
    expect($log->status)->toBe($status)
        ->and($log->http_status)->toBe($httpStatus)
        ->and($log->type)->toBe(GoogleBooksRequestType::Isbn);
})->with([
    'quota' => [fn () => Http::response(['error' => ['code' => 429]], 429), GoogleBooksRequestStatus::RateLimited, 429],
    'forbidden' => [fn () => Http::response(['error' => ['code' => 403]], 403), GoogleBooksRequestStatus::ClientError, 403],
    'server error' => [fn () => Http::response('Backend Error', 500), GoogleBooksRequestStatus::ServerError, 500],
    'unavailable' => [fn () => Http::response('', 503), GoogleBooksRequestStatus::ServerError, 503],
    'timeout' => [fn () => Http::failedConnection('cURL error 28: Operation timed out'), GoogleBooksRequestStatus::Timeout, null],
    'not JSON' => [fn () => Http::response('<html>oops</html>', 200), GoogleBooksRequestStatus::InvalidResponse, 200],
    'items not a list' => [fn () => Http::response(['totalItems' => 1, 'items' => 'x']), GoogleBooksRequestStatus::InvalidResponse, 200],
    'volume without id' => [fn () => Http::response(googleSearch([['volumeInfo' => ['title' => 'x']]])), GoogleBooksRequestStatus::InvalidResponse, 200],
    'volume without title' => [fn () => Http::response(googleSearch([googleVolume(volumeInfo: ['title' => ''])])), GoogleBooksRequestStatus::InvalidResponse, 200],
    'volumeInfo not an object' => [fn () => Http::response(googleSearch([['id' => 'abc', 'volumeInfo' => 'x']])), GoogleBooksRequestStatus::InvalidResponse, 200],
]);

it('gives a French message for each failure', function () {
    expect((new GoogleBooksUnavailableException(GoogleBooksRequestStatus::RateLimited))->getMessage())
        ->toBe('Le quota de recherches Google Books est atteint. Réessayez plus tard ou saisissez le livre à la main.')
        ->and((new GoogleBooksUnavailableException(GoogleBooksRequestStatus::Timeout))->getMessage())
        ->toBe('Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.');
});

it('keeps the found volume in a short server cache', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([googleVolume('zyTCAlFPjgYC')]))]);
    $user = User::factory()->create();

    findByIsbn('9782070368228', $user);
    $volume = app(GoogleBooksService::class)->trustedVolume('zyTCAlFPjgYC', $user);

    expect($volume->raw)->toBe(googleVolume('zyTCAlFPjgYC'));
    Http::assertSentCount(1);
});

it('fetches a volume that is no longer cached', function () {
    Http::fake(['www.googleapis.com/books/v1/volumes/zyTCAlFPjgYC*' => Http::response(googleVolume('zyTCAlFPjgYC'))]);
    $user = User::factory()->create();

    $volume = app(GoogleBooksService::class)->trustedVolume('zyTCAlFPjgYC', $user);

    expect($volume->title)->toBe('Le Hobbit')
        ->and(Cache::has('google-books:volume:zyTCAlFPjgYC'))->toBeTrue();
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes/zyTCAlFPjgYC'));

    $log = GoogleBooksRequest::query()->sole();
    expect($log->type)->toBe(GoogleBooksRequestType::Volume)
        ->and($log->query)->toBe('zyTCAlFPjgYC')
        ->and($log->status)->toBe(GoogleBooksRequestStatus::Found)
        ->and($log->user_id)->toBe($user->id);
});

it('refuses to trust an unknown or unreachable volume', function (mixed $response, GoogleBooksRequestStatus $status) {
    Http::fake(['www.googleapis.com/*' => $response]);

    expect(fn () => app(GoogleBooksService::class)->trustedVolume('zyTCAlFPjgYC', User::factory()->create()))
        ->toThrow(ValidationException::class);

    expect(GoogleBooksRequest::query()->sole()->status)->toBe($status);
})->with([
    'unknown' => [fn () => Http::response(['error' => ['code' => 404]], 404), GoogleBooksRequestStatus::NotFound],
    'server error' => [fn () => Http::response('', 500), GoogleBooksRequestStatus::ServerError],
    'timeout' => [fn () => Http::failedConnection(), GoogleBooksRequestStatus::Timeout],
    'another volume' => [fn () => Http::response(googleVolume('another')), GoogleBooksRequestStatus::InvalidResponse],
]);
