<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Paul, with two bookshelves, two books, two tool shelves and two tools.
 *
 * @return array{member: User, fantasy: Bookshelf, polars: Bookshelf, hobbit: Book, dune: Book, workbench: ToolShelf, garden: ToolShelf, drill: Tool, rake: Tool}
 */
function paulsHouse(): array
{
    Storage::fake('public');
    $member = User::factory()->create(['name' => 'Paul', 'email' => 'paul@example.test']);
    $fantasy = Bookshelf::factory()->for($member)->create(['name' => 'Fantasy']);
    $polars = Bookshelf::factory()->for($member)->create(['name' => 'Polars']);
    $hobbit = Book::factory()->for($member)->create([
        'title' => 'Le Hobbit',
        'author' => 'J. R. R. Tolkien',
        'isbn' => '207036822X',
        'cover_path' => 'covers/hobbit.jpg',
        'google_books_data' => ['secret' => 'raw'],
    ]);
    $hobbit->bookshelves()->attach($fantasy);
    $dune = Book::factory()->for($member)->create(['title' => 'Dune', 'author' => 'Frank Herbert']);
    $dune->bookshelves()->attach($polars);

    $workbench = ToolShelf::factory()->for($member)->create(['name' => 'Établi']);
    $garden = ToolShelf::factory()->for($member)->create(['name' => 'Jardin']);
    $drill = toolWithPhotos($member, ['drill']);
    $drill->brand()->associate(Brand::factory()->create(['name' => 'Bosch']))->save();
    $drill->shelves()->attach($workbench);
    $rake = Tool::factory()->for($member)->create(['name' => 'Râteau']);
    $rake->shelves()->attach($garden);

    return compact('member', 'fantasy', 'polars', 'hobbit', 'dune', 'workbench', 'garden', 'drill', 'rake');
}

it('redirects a guest to the login page', function () {
    $house = paulsHouse();

    get(route('family.members.show', $house['member']))->assertRedirect(route('login'));
});

it('shows the library and the garage of another member, read-only', function (bool $admin) {
    $house = paulsHouse();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());
    // Objects of a third member never show up.
    Book::factory()->for(User::factory())->create(['title' => 'Autre livre']);

    get(route('family.members.show', $house['member']))
        ->assertOk()
        ->assertDontSee('paul@example.test')
        ->assertDontSee('secret')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Family/Show')
            ->where('member', ['id' => $house['member']->id, 'name' => 'Paul'])
            ->where('own', false)
            ->where('tab', 'library')
            ->where('filters', ['bookshelf' => null, 'shelf' => null])
            ->where('bookshelves', [
                ['id' => $house['fantasy']->id, 'name' => 'Fantasy', 'position' => 1],
                ['id' => $house['polars']->id, 'name' => 'Polars', 'position' => 2],
            ])
            ->where('books', [
                [
                    'id' => $house['dune']->id,
                    'title' => 'Dune',
                    'author' => 'Frank Herbert',
                    'isbn' => null,
                    'cover_url' => null,
                    'bookshelves' => [['id' => $house['polars']->id, 'name' => 'Polars']],
                ],
                [
                    'id' => $house['hobbit']->id,
                    'title' => 'Le Hobbit',
                    'author' => 'J. R. R. Tolkien',
                    'isbn' => '207036822X',
                    'cover_url' => Storage::disk('public')->url('covers/hobbit.jpg'),
                    'bookshelves' => [['id' => $house['fantasy']->id, 'name' => 'Fantasy']],
                ],
            ])
            ->where('toolShelves', [
                ['id' => $house['workbench']->id, 'name' => 'Établi', 'position' => 1],
                ['id' => $house['garden']->id, 'name' => 'Jardin', 'position' => 2],
            ])
            ->where('tools', [
                [
                    'id' => $house['drill']->id,
                    'name' => 'Perceuse',
                    'brand' => ['id' => $house['drill']->brand_id, 'name' => 'Bosch'],
                    'thumbnail_url' => Storage::disk('public')->url('tools/drill.jpg'),
                    'shelves' => [['id' => $house['workbench']->id, 'name' => 'Établi']],
                ],
                [
                    'id' => $house['rake']->id,
                    'name' => 'Râteau',
                    'brand' => null,
                    'thumbnail_url' => null,
                    'shelves' => [['id' => $house['garden']->id, 'name' => 'Jardin']],
                ],
            ]));
})->with(['user' => [false], 'admin' => [true]]);

it('marks the page of the signed-in member as their own', function () {
    $house = paulsHouse();
    actingAs($house['member']);

    get(route('family.members.show', $house['member']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('own', true)->has('books', 2)->has('tools', 2));
});

it('answers 404 for a member that does not exist', function () {
    signIn();

    get(route('family.members.show', 999))->assertNotFound();
});

it('filters the books and the tools of the member by shelf', function () {
    $house = paulsHouse();
    signIn();

    get(route('family.members.show', [$house['member'], 'bookshelf' => $house['fantasy']->id, 'shelf' => $house['garden']->id, 'tab' => 'garage']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'garage')
            ->where('filters', ['bookshelf' => $house['fantasy']->id, 'shelf' => $house['garden']->id])
            ->has('books', 1)
            ->where('books.0.title', 'Le Hobbit')
            ->has('tools', 1)
            ->where('tools.0.name', 'Râteau')
            ->has('bookshelves', 2)
            ->has('toolShelves', 2));
});

it('opens the library tab for any other tab value', function (string $tab) {
    $house = paulsHouse();
    signIn();

    get(route('family.members.show', [$house['member'], 'tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'library'));
})->with(['library', 'admin', '']);

/**
 * A filter value that is not one of the shelves of `$member` in `$module`.
 */
function foreignShelfFilter(string $case, string $module, User $member, User $viewer): mixed
{
    $shelf = $module === 'library' ? Bookshelf::class : ToolShelf::class;
    $otherModule = $module === 'library' ? ToolShelf::class : Bookshelf::class;

    return match ($case) {
        'shelf of the viewer' => $shelf::factory()->for($viewer)->create()->id,
        'shelf of a third member' => $shelf::factory()->for(User::factory())->create()->id,
        // A new id, so that no shelf of `$module` has the same.
        'shelf of the member in the other module' => tap(
            $otherModule::factory()->for($member)->create()->id,
            fn (int $id) => expect($shelf::query()->find($id))->toBeNull(),
        ),
        'unknown' => 999,
        'not an integer' => 'fantasy',
        'array' => [1],
        default => throw new InvalidArgumentException($case),
    };
}

it('answers 404 for a shelf filter that is not one of the member', function (string $module, string $case) {
    $house = paulsHouse();
    $viewer = signIn();
    $parameter = $module === 'library' ? 'bookshelf' : 'shelf';
    $value = foreignShelfFilter($case, $module, $house['member'], $viewer);
    expect($value)->not->toBeNull();

    get(route('family.members.show', [$house['member'], $parameter => $value]))->assertNotFound();
})->with(['library', 'garage'])->with([
    'shelf of the viewer',
    'shelf of a third member',
    'shelf of the member in the other module',
    'unknown',
    'not an integer',
    'array',
]);

it('loads the page with the same number of queries whatever the number of books and tools', function () {
    $countQueries = function (int $count): int {
        $member = User::factory()->create();
        $bookshelves = collect([Bookshelf::factory()->for($member)->create(), Bookshelf::factory()->for($member)->create()]);
        $shelves = collect([ToolShelf::factory()->for($member)->create(), ToolShelf::factory()->for($member)->create()]);
        Book::factory()->for($member)->count($count)->create()->each(fn (Book $book) => $book->bookshelves()->attach($bookshelves));
        Tool::factory()->for($member)->count($count)->create()->each(function (Tool $tool, int $index) use ($shelves) {
            $tool->brand()->associate(Brand::factory()->create())->save();
            $tool->shelves()->attach($shelves);
            $tool->photos()->create(['path' => "tools/{$index}.jpg", 'position' => 1]);
        });
        actingAs(User::factory()->create());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        get(route('family.members.show', $member))->assertOk();

        return $queries;
    };

    $withOne = $countQueries(1);
    expect($countQueries(5))->toBe($withOne);
});
