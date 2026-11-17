<?php

use App\Enums\Garage\ToolShelfMoveDirection;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Services\Garage\ToolShelfService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Runs the change and returns the SQL of its queries, without identifier quotes.
 *
 * @return list<string>
 */
function toolShelfServiceQueries(Closure $change): array
{
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries) {
        $queries[] = str_replace(['"', '`'], '', $query->sql);
    });
    $change();

    return $queries;
}

it('locks the row of the owner before any other query on every write to their tool shelves', function (Closure $change) {
    $user = User::factory()->create();
    $newcomer = User::factory()->create();
    $first = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    $service = app(ToolShelfService::class);

    $queries = toolShelfServiceQueries(fn () => $change($service, $user, $newcomer, $first));

    $ownerLock = $queries[0] ?? '';
    expect($ownerLock)->toStartWith('select * from users where users.id = ?');

    if (DB::connection()->getDriverName() === 'mysql') {
        expect($ownerLock)->toEndWith('for update');
    }
})->with([
    'create' => [fn (ToolShelfService $service, User $user) => $service->create($user, 'Jardin')],
    'create the first one' => [fn (ToolShelfService $service, User $user, User $newcomer) => $service->create($newcomer, 'Électroportatif')],
    'rename' => [fn (ToolShelfService $service, User $user, User $newcomer, ToolShelf $first) => $service->rename($first, 'Outillage')],
    'move' => [fn (ToolShelfService $service, User $user, User $newcomer, ToolShelf $first) => $service->move($first, ToolShelfMoveDirection::Down)],
    'delete' => [fn (ToolShelfService $service, User $user, User $newcomer, ToolShelf $first) => $service->delete($first)],
]);

it('gives consecutive positions to tool shelves created one after another from an empty garage', function () {
    $user = User::factory()->create();
    $service = app(ToolShelfService::class);

    $service->create($user, 'Électroportatif');
    $service->create($user, 'Manuel');

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel']);
});
