<?php

use App\Enums\Library\BookshelfMoveDirection;
use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Services\Library\BookshelfService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Runs the change and returns the SQL of its queries, without identifier quotes.
 *
 * @return list<string>
 */
function bookshelfServiceQueries(Closure $change): array
{
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries) {
        $queries[] = str_replace(['"', '`'], '', $query->sql);
    });
    $change();

    return $queries;
}

it('locks the row of the owner before any other query on every write to their bookshelves', function (Closure $change) {
    $user = User::factory()->create();
    $newcomer = User::factory()->create();
    $first = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    $service = app(BookshelfService::class);

    $queries = bookshelfServiceQueries(fn () => $change($service, $user, $newcomer, $first));

    $ownerLock = $queries[0] ?? '';
    expect($ownerLock)->toStartWith('select * from users where users.id = ?');

    if (DB::connection()->getDriverName() === 'mysql') {
        expect($ownerLock)->toEndWith('for update');
    }
})->with([
    'create' => [fn (BookshelfService $service, User $user) => $service->create($user, 'Coup de cœur')],
    'create the first one' => [fn (BookshelfService $service, User $user, User $newcomer) => $service->create($newcomer, 'Fantasy')],
    'rename' => [fn (BookshelfService $service, User $user, User $newcomer, Bookshelf $first) => $service->rename($first, 'Romans')],
    'move' => [fn (BookshelfService $service, User $user, User $newcomer, Bookshelf $first) => $service->move($first, BookshelfMoveDirection::Down)],
    'delete' => [fn (BookshelfService $service, User $user, User $newcomer, Bookshelf $first) => $service->delete($first)],
]);

it('gives consecutive positions to bookshelves created one after another from an empty library', function () {
    $user = User::factory()->create();
    $service = app(BookshelfService::class);

    $service->create($user, 'Fantasy');
    $service->create($user, 'Polars');

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars']);
});
