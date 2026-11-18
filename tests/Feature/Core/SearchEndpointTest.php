<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Library\Book;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/**
 * @return TestResponse<JsonResponse>
 */
function search(mixed $query): TestResponse
{
    return getJson(route('search', ['q' => $query]));
}

it('refuses a guest', function () {
    Book::factory()->create(['title' => 'Le Hobbit']);

    search('hobbit')->assertUnauthorized();
    get(route('search', ['q' => 'hobbit']))->assertRedirect(route('login'));
});

it('validates the length of the query', function (mixed $query) {
    signIn();

    search($query)->assertUnprocessable()->assertJsonValidationErrors(['q']);
})->with([
    'missing' => [null],
    'one character' => ['a'],
    'one character once trimmed' => ['  a  '],
    'too long' => [str_repeat('a', 101)],
    'array' => [['hobbit']],
]);

it('explains the validation errors in French', function () {
    signIn();

    search('a')->assertJsonValidationErrors(['q' => 'Le texte de recherche doit contenir au moins 2 caractères.']);
});

it('accepts a query of 2 to 100 characters', function (string $query) {
    signIn();

    search($query)->assertOk()->assertExactJson(['groups' => []]);
})->with(['two characters' => ['ab'], 'hundred characters' => [str_repeat('é', 100)]]);

it('answers the results of every module grouped, in the registry order', function () {
    Storage::fake('public');
    $paul = User::factory()->create(['name' => 'Paul Terrier']);
    $book = Book::factory()->for($paul)->create(['title' => 'Terre des hommes', 'author' => 'Saint-Exupéry', 'isbn' => '9782070360246']);
    $tool = Tool::factory()->for($paul)->create(['name' => 'Tarière', 'brand_id' => Brand::factory()->create(['name' => 'Terratek'])->id]);
    $book->forceFill(['google_books_data' => ['publisher' => 'Gallimard-secret']])->save();
    actingAs(User::factory()->create(['name' => 'Zoé', 'email' => 'zoe@example.com']));

    $response = search('ter')->assertOk();

    expect($response->json())->toBe(['groups' => [
        [
            'key' => 'books',
            'label' => 'Livres',
            'icon' => 'i-lucide-book',
            'results' => [[
                'type' => 'book',
                'id' => $book->id,
                'label' => 'Terre des hommes',
                'description' => 'Saint-Exupéry · chez Paul Terrier',
                'url' => "/library/books/{$book->id}",
                'image' => null,
            ]],
        ],
        [
            'key' => 'tools',
            'label' => 'Outils',
            'icon' => 'i-lucide-wrench',
            'results' => [[
                'type' => 'tool',
                'id' => $tool->id,
                'label' => 'Tarière',
                'description' => 'Terratek · chez Paul Terrier',
                'url' => "/garage/tools/{$tool->id}",
                'image' => null,
            ]],
        ],
        [
            'key' => 'members',
            'label' => 'Membres',
            'icon' => 'i-lucide-user',
            'results' => [[
                'type' => 'member',
                'id' => $paul->id,
                'label' => 'Paul Terrier',
                'description' => '1 livre · 1 outil',
                'url' => "/family/{$paul->id}",
                'image' => null,
            ]],
        ],
    ]]);
    $response->assertDontSee('Gallimard-secret')
        ->assertDontSee('@example.com')
        ->assertDontSee('google_books_data')
        ->assertDontSee('user_id')
        ->assertDontSee('created_at')
        ->assertDontSee('9782070360246');
});

it('searches the trimmed query and omits the empty groups', function () {
    Book::factory()->create(['title' => 'Le Hobbit']);
    signIn();

    $groups = search('  hobbit  ')->assertOk()->json('groups');

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe('books');
});

it('returns at most five results per group', function () {
    foreach (range(1, 7) as $number) {
        Book::factory()->create(['title' => "Roman {$number}"]);
    }
    signIn();

    expect(search('roman')->json('groups.0.results'))->toHaveCount(5);
});

it('runs a number of queries independent of the number of results', function () {
    Storage::fake('public');
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        search('zz')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $addMatches = function (int $count): void {
        foreach (range(1, $count) as $number) {
            // Not starting with the term: both ranks are queried whatever the count.
            $owner = User::factory()->create(['name' => "Membre zz {$number}"]);
            Book::factory()->for($owner)->create(['title' => "Livre zz {$number}", 'cover_path' => "covers/{$number}.jpg"]);
            $tool = toolWithPhotos($owner, ["p{$number}a", "p{$number}b"], "Outil {$number}");
            $tool->brand()->associate(Brand::factory()->create())->save();
            $tool->accessories()->create(['name' => "Accessoire zz {$number}", 'position' => 1]);
        }
    };
    signIn();

    $addMatches(1);
    $withOneMatch = $countQueries();
    $addMatches(5);
    $withManyMatches = $countQueries();

    expect($withManyMatches)->toBe($withOneMatch);
});

it('limits the searches of each member', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    actingAs($user);
    foreach (range(1, 60) as $attempt) {
        search('hobbit')->assertOk();
    }

    search('hobbit')->assertTooManyRequests()
        ->assertExactJson(['message' => 'Trop de recherches. Veuillez patienter une minute avant de réessayer.']);

    actingAs($other);
    search('hobbit')->assertOk();
});
