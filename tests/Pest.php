<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Library\Bookshelf;
use App\Models\User;

use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Headers of an Inertia visit made by a page built with the current assets.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

/**
 * Names of the bookshelves of a user, in their order (positions must be 1..n).
 *
 * @return list<string>
 */
function bookshelfNames(User $user): array
{
    $bookshelves = Bookshelf::query()
        ->whereBelongsTo($user)
        ->orderBy('position')
        ->get();

    expect($bookshelves->pluck('position')->all())->toBe($bookshelves->isEmpty() ? [] : range(1, $bookshelves->count()));

    return $bookshelves->pluck('name')->values()->all();
}

/**
 * Creates a member and signs them in.
 */
function signIn(): User
{
    $user = User::factory()->create();
    actingAs($user);

    return $user;
}
