<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

/**
 * Paul owns a bookshelf with a book and a tool shelf with a tool.
 *
 * @return array{owner: User, bookshelf: Bookshelf, book: Book, shelf: ToolShelf, tool: Tool}
 */
function readOnlyHouse(): array
{
    Storage::fake('public');
    $owner = User::factory()->create(['name' => 'Paul', 'email' => 'paul@example.test']);
    $bookshelf = Bookshelf::factory()->for($owner)->create(['name' => 'Fantasy']);
    $book = Book::factory()->for($owner)->create([
        'title' => 'Le Hobbit',
        'author' => 'J. R. R. Tolkien',
        'cover_path' => 'covers/hobbit.jpg',
        'google_books_data' => ['secret' => 'raw'],
    ]);
    $book->bookshelves()->attach($bookshelf);
    $shelf = ToolShelf::factory()->for($owner)->create(['name' => 'Établi']);
    $tool = toolWithPhotos($owner, ['drill']);
    $tool->brand()->associate(Brand::factory()->create(['name' => 'Bosch']))->save();
    $tool->accessories()->create(['name' => 'Mallette', 'position' => 1]);
    $tool->shelves()->attach($shelf);

    return compact('owner', 'bookshelf', 'book', 'shelf', 'tool');
}

/**
 * Every route that writes (or opens a form to write) an object of the house.
 *
 * @return array<string, Closure(array{owner: User, bookshelf: Bookshelf, book: Book, shelf: ToolShelf, tool: Tool}): TestResponse<Response>>
 */
function readOnlyWriteCalls(): array
{
    return [
        'book edit' => fn (array $house) => get(route('library.books.edit', $house['book'])),
        'book update' => fn (array $house) => patch(route('library.books.update', $house['book']), ['title' => 'Volé', 'author' => 'X']),
        'book destroy' => fn (array $house) => delete(route('library.books.destroy', $house['book'])),
        'bookshelf update' => fn (array $house) => patch(route('library.bookshelves.update', $house['bookshelf']), ['name' => 'Volée']),
        'bookshelf destroy' => fn (array $house) => delete(route('library.bookshelves.destroy', $house['bookshelf'])),
        'bookshelf move' => fn (array $house) => patch(route('library.bookshelves.move', $house['bookshelf']), ['direction' => 'down']),
        'tool edit' => fn (array $house) => get(route('garage.tools.edit', $house['tool'])),
        'tool update' => fn (array $house) => patch(route('garage.tools.update', $house['tool']), ['name' => 'Volé']),
        'tool destroy' => fn (array $house) => delete(route('garage.tools.destroy', $house['tool'])),
        'tool shelf update' => fn (array $house) => patch(route('garage.shelves.update', $house['shelf']), ['name' => 'Volée']),
        'tool shelf destroy' => fn (array $house) => delete(route('garage.shelves.destroy', $house['shelf'])),
        'tool shelf move' => fn (array $house) => patch(route('garage.shelves.move', $house['shelf']), ['direction' => 'down']),
    ];
}

it('keeps every write route of another member at 404 now that their objects are readable', function (string $call, bool $admin) {
    $house = readOnlyHouse();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());
    get(route('family.members.show', $house['owner']))->assertOk();

    readOnlyWriteCalls()[$call]($house)->assertNotFound();

    expect($house['book']->fresh()?->title)->toBe('Le Hobbit')
        ->and($house['book']->fresh()?->bookshelves()->count())->toBe(1)
        ->and($house['bookshelf']->fresh()?->name)->toBe('Fantasy')
        ->and($house['tool']->fresh()?->name)->toBe('Perceuse')
        ->and(toolAccessoryNames($house['tool']))->toBe(['Mallette'])
        ->and(toolPhotoPaths($house['tool']))->toBe(['tools/drill.jpg'])
        ->and($house['shelf']->fresh()?->name)->toBe('Établi');
})->with(array_keys(readOnlyWriteCalls()))->with(['user' => [false], 'admin' => [true]]);

it('shows the page of a book of another member with its owner, read-only and without internal data', function (bool $admin) {
    $house = readOnlyHouse();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    get(route('library.books.show', $house['book']))
        ->assertOk()
        ->assertDontSee('paul@example.test')
        ->assertDontSee('secret')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Show')
            ->where('book', [
                'id' => $house['book']->id,
                'title' => 'Le Hobbit',
                'author' => 'J. R. R. Tolkien',
                'isbn' => null,
                'cover_url' => Storage::disk('public')->url('covers/hobbit.jpg'),
                'bookshelves' => [['id' => $house['bookshelf']->id, 'name' => 'Fantasy']],
                'comment' => null,
            ])
            ->where('owner', ['id' => $house['owner']->id, 'name' => 'Paul'])
            ->where('editable', false));
})->with(['user' => [false], 'admin' => [true]]);

it('shows the page of a tool of another member with its owner, read-only and without internal data', function (bool $admin) {
    $house = readOnlyHouse();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    get(route('garage.tools.show', $house['tool']))
        ->assertOk()
        ->assertDontSee('paul@example.test')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Tools/Show')
            ->where('tool', [
                'id' => $house['tool']->id,
                'name' => 'Perceuse',
                'brand' => ['id' => $house['tool']->brand_id, 'name' => 'Bosch'],
                'thumbnail_url' => Storage::disk('public')->url('tools/drill.jpg'),
                'shelves' => [['id' => $house['shelf']->id, 'name' => 'Établi']],
                'photos' => [['id' => $house['tool']->photos()->value('id'), 'url' => Storage::disk('public')->url('tools/drill.jpg')]],
                'accessories' => [['id' => $house['tool']->accessories()->value('id'), 'name' => 'Mallette']],
                'comment' => null,
            ])
            ->where('owner', ['id' => $house['owner']->id, 'name' => 'Paul'])
            ->where('editable', false));
})->with(['user' => [false], 'admin' => [true]]);

it('marks the pages of their own book and tool as editable for the owner', function () {
    $house = readOnlyHouse();
    actingAs($house['owner']);

    get(route('library.books.show', $house['book']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('owner', ['id' => $house['owner']->id, 'name' => 'Paul'])
            ->where('editable', true));
    get(route('garage.tools.show', $house['tool']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('owner', ['id' => $house['owner']->id, 'name' => 'Paul'])
            ->where('editable', true));
});
