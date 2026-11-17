<?php

use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\Book;
use App\Models\Library\GoogleBooksRequest;
use App\Services\Library\GoogleBooksService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\patch;
use function Pest\Laravel\post;

const GOOGLE_VOLUME_URL = 'www.googleapis.com/books/v1/volumes/zyTCAlFPjgYC*';
const GOOGLE_THUMBNAIL = 'books.google.com/books/content*';

beforeEach(function () {
    Storage::fake('public');
    Http::preventStrayRequests();
});

/**
 * Fields of the form after a successful lookup of the volume zyTCAlFPjgYC.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function googleBookForm(array $overrides = []): array
{
    return [
        'title' => 'Le Hobbit',
        'author' => 'J. R. R. Tolkien',
        'isbn' => '9782070368228',
        'google_volume_id' => 'zyTCAlFPjgYC',
        'use_google_cover' => true,
        ...$overrides,
    ];
}

function fakeGoogleVolumeAndCover(): void
{
    Http::fake([
        GOOGLE_VOLUME_URL => Http::response(googleVolume('zyTCAlFPjgYC')),
        GOOGLE_THUMBNAIL => Http::response(jpegBytes(1280, 1920), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

it('stores the raw data fetched by the server and the downloaded cover', function () {
    fakeGoogleVolumeAndCover();
    $user = signIn();
    $forged = ['volumeInfo' => ['title' => 'Forgé par le navigateur']];

    post(route('library.books.store'), googleBookForm([
        'title' => 'Le Hobbit (corrigé)',
        'google_books_data' => $forged,
    ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le livre « Le Hobbit (corrigé) » a été ajouté.']);

    $book = Book::query()->sole();
    expect($book->user_id)->toBe($user->id)
        ->and($book->title)->toBe('Le Hobbit (corrigé)')
        ->and($book->google_books_data)->toBe(googleVolume('zyTCAlFPjgYC'))
        ->and($book->cover_path)->toStartWith('covers/')
        ->and(storedImage((string) $book->cover_path))->toBe([800, 1200, 'mime' => 'image/jpeg']);

    // The cover is downloaded over https even though Google lists it in http.
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://books.google.com/books/content?id=zyTCAlFPjgYC'));
    expect(GoogleBooksRequest::query()->pluck('type')->all())->toBe([GoogleBooksRequestType::Volume, GoogleBooksRequestType::Cover]);
});

it('reuses the volume cached by the lookup instead of calling Google again', function () {
    Http::fake([
        'www.googleapis.com/books/v1/volumes?*' => Http::response(googleSearch([googleVolume('zyTCAlFPjgYC')])),
    ]);
    $user = signIn();
    app(GoogleBooksService::class)->findByIsbn('9782070368228', $user);

    post(route('library.books.store'), googleBookForm(['use_google_cover' => false]))->assertSessionHasNoErrors();

    expect(Book::query()->sole()->google_books_data)->toBe(googleVolume('zyTCAlFPjgYC'))
        ->and(Book::query()->sole()->cover_path)->toBeNull();
    Http::assertSentCount(1);
});

it('prefers an uploaded cover to the Google one', function () {
    fakeGoogleVolumeAndCover();
    signIn();

    post(route('library.books.store'), googleBookForm(['cover' => UploadedFile::fake()->image('mine.jpg', 400, 600)]))
        ->assertSessionHasNoErrors();

    expect(storedImage((string) Book::query()->sole()->cover_path))->toBe([400, 600, 'mime' => 'image/jpeg']);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'books/content'));
});

it('saves the book without cover when the Google cover cannot be downloaded', function () {
    Http::fake([
        GOOGLE_VOLUME_URL => Http::response(googleVolume('zyTCAlFPjgYC')),
        GOOGLE_THUMBNAIL => Http::response('', 500),
    ]);
    signIn();

    post(route('library.books.store'), googleBookForm())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Le livre « Le Hobbit » a été ajouté, mais la couverture Google Books n’a pas pu être récupérée.',
        ]);

    expect(Book::query()->sole()->cover_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses to save when the volume cannot be fetched by the server', function (mixed $response) {
    Http::fake([GOOGLE_VOLUME_URL => $response]);
    signIn();

    post(route('library.books.store'), googleBookForm())
        ->assertSessionHasErrors(['google_volume_id' => 'Les informations Google Books n’ont pas pu être vérifiées. Réessayez, ou fermez l’encart Google Books pour enregistrer votre saisie seule.']);

    expect(Book::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'unknown volume' => [fn () => Http::response('', 404)],
    'unavailable' => [fn () => Http::response('', 503)],
    'timeout' => [fn () => Http::failedConnection()],
]);

it('rejects a malformed volume identifier without calling Google', function (string $volumeId) {
    Http::fake();
    signIn();

    post(route('library.books.store'), googleBookForm(['google_volume_id' => $volumeId]))
        ->assertSessionHasErrors('google_volume_id');

    Http::assertNothingSent();
    expect(Book::query()->count())->toBe(0);
})->with([
    'path traversal' => ['../../evil'],
    'query' => ['abc?key=x'],
    'too long' => [str_repeat('a', 65)],
]);

it('replaces the raw data and the cover of an edited book', function () {
    fakeGoogleVolumeAndCover();
    $user = signIn();
    Storage::disk('public')->put('covers/old.jpg', 'old');
    $book = Book::factory()->for($user)->create(['cover_path' => 'covers/old.jpg']);
    $book->forceFill(['google_books_data' => ['id' => 'old']])->save();

    patch(route('library.books.update', $book), googleBookForm(['remove_cover' => true]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le livre « Le Hobbit » a été modifié.']);

    $book->refresh();
    expect($book->google_books_data)->toBe(googleVolume('zyTCAlFPjgYC'))
        ->and($book->cover_path)->not->toBe('covers/old.jpg')
        ->and(storedImage((string) $book->cover_path))->toBe([800, 1200, 'mime' => 'image/jpeg']);
    Storage::disk('public')->assertMissing('covers/old.jpg');
});

it('keeps the raw data and the cover of an edited book when the Google cover fails', function () {
    Http::fake([
        GOOGLE_VOLUME_URL => Http::response(googleVolume('zyTCAlFPjgYC')),
        GOOGLE_THUMBNAIL => Http::failedConnection(),
    ]);
    $user = signIn();
    Storage::disk('public')->put('covers/old.jpg', 'old');
    $book = Book::factory()->for($user)->create(['cover_path' => 'covers/old.jpg']);

    patch(route('library.books.update', $book), googleBookForm())
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Le livre « Le Hobbit » a été modifié, mais la couverture Google Books n’a pas pu être récupérée.',
        ]);

    expect($book->refresh()->cover_path)->toBe('covers/old.jpg');
    Storage::disk('public')->assertExists('covers/old.jpg');
});

it('keeps the raw data of a book edited by hand', function () {
    Http::fake();
    $user = signIn();
    $book = Book::factory()->for($user)->create();
    $book->forceFill(['google_books_data' => googleVolume()])->save();

    patch(route('library.books.update', $book), [
        'title' => 'Titre corrigé',
        'author' => 'Auteur',
        'use_google_cover' => true,
        'google_books_data' => ['forged' => true],
    ])->assertSessionHasNoErrors();

    expect($book->refresh()->google_books_data)->toBe(googleVolume())
        ->and($book->cover_path)->toBeNull();
    Http::assertNothingSent();
});

it('never calls Google for a book typed by hand', function () {
    Http::fake();
    signIn();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'google_books_data' => ['x' => 1]])
        ->assertSessionHasNoErrors();

    expect(Book::query()->sole()->google_books_data)->toBeNull();
    Http::assertNothingSent();
    expect(GoogleBooksRequest::query()->count())->toBe(0);
});
