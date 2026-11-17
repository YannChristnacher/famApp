<?php

use App\Models\Library\GoogleBooksRequest;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.google_books.key' => 'secret-key']);
});

/**
 * @return TestResponse<JsonResponse>
 */
function lookup(string $isbn): TestResponse
{
    return getJson(route('library.google-books.lookup', ['isbn' => $isbn]));
}

it('redirects a guest to the login page without calling Google', function () {
    Http::fake();

    get(route('library.google-books.lookup', ['isbn' => '9782070368228']))->assertRedirect(route('login'));
    lookup('9782070368228')->assertUnauthorized();

    Http::assertNothingSent();
});

it('answers the minimal prefill of the first volume', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([
        googleVolume('zyTCAlFPjgYC', ['authors' => ['Terry Pratchett', 'Neil Gaiman']]),
    ]))]);
    $user = signIn();

    $response = lookup('978-2-07-036822-8')->assertOk();

    expect($response->json())->toBe([
        'volume_id' => 'zyTCAlFPjgYC',
        'title' => 'Le Hobbit',
        'author' => 'Terry Pratchett, Neil Gaiman',
        'cover_url' => 'https://books.google.com/books/content?id=zyTCAlFPjgYC&printsec=frontcover&img=1&zoom=1&source=gbs_api',
    ]);
    $response->assertDontSee('secret-key')->assertDontSee('Gallimard');
    Http::assertSent(fn (Request $request) => $request['q'] === 'isbn:9782070368228');
    expect(GoogleBooksRequest::query()->sole()->user_id)->toBe($user->id);
});

it('answers 404 with a French message when Google knows no book', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([]))]);
    signIn();

    lookup('9782070368228')->assertNotFound()
        ->assertExactJson(['message' => 'Aucun livre trouvé pour cet ISBN. Vous pouvez le saisir à la main.']);
});

it('answers 503 with a French message when Google fails', function (mixed $response, string $message) {
    Http::fake(['www.googleapis.com/*' => $response]);
    signIn();

    lookup('9782070368228')->assertServiceUnavailable()->assertExactJson(['message' => $message]);
})->with([
    'quota' => [fn () => Http::response('', 429), 'Le quota de recherches Google Books est atteint. Réessayez plus tard ou saisissez le livre à la main.'],
    'server error' => [fn () => Http::response('', 500), 'Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.'],
    'timeout' => [fn () => Http::failedConnection(), 'Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.'],
    'malformed' => [fn () => Http::response('nope'), 'Google Books ne répond pas pour le moment. Réessayez plus tard ou saisissez le livre à la main.'],
]);

it('rejects a missing or invalid ISBN without calling Google', function (string $isbn, string $message) {
    Http::fake();
    signIn();

    lookup($isbn)->assertUnprocessable()->assertJsonValidationErrors(['isbn' => $message]);

    Http::assertNothingSent();
    expect(GoogleBooksRequest::query()->count())->toBe(0);
})->with([
    'missing' => ['', 'Le champ ISBN est obligatoire.'],
    'wrong check digit' => ['9782070368221', 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
    'letters' => ['abc', 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
]);

it('limits the lookups of each member', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(googleSearch([googleVolume()]))]);
    $user = signIn();

    foreach (range(1, 10) as $attempt) {
        lookup('9782070368228')->assertOk();
    }
    lookup('9782070368228')->assertTooManyRequests()
        ->assertExactJson(['message' => 'Trop de recherches. Veuillez patienter une minute avant de réessayer.']);
    Http::assertSentCount(10);

    actingAs(User::factory()->create());
    lookup('9782070368228')->assertOk();
    expect(GoogleBooksRequest::query()->where('user_id', $user->id)->count())->toBe(10);
});
