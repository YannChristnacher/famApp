<?php

use App\Models\Garage\Tool;
use App\Models\Library\Book;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects a guest to the login page', function () {
    get(route('family.index'))->assertRedirect(route('login'));
});

it('lists every member by name with their numbers of books and tools, and nothing else', function (bool $admin) {
    $viewer = $admin ? User::factory()->admin()->create(['name' => 'Marie']) : User::factory()->create(['name' => 'Marie']);
    $paul = User::factory()->create(['name' => 'Paul', 'email' => 'paul@example.test']);
    $anne = User::factory()->admin()->create(['name' => 'Anne']);
    Book::factory()->for($paul)->count(3)->create();
    Tool::factory()->for($paul)->count(2)->create();
    Book::factory()->for($viewer)->create();
    actingAs($viewer);

    get(route('family.index'))
        ->assertOk()
        ->assertDontSee('paul@example.test')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Core/Family/Index')
            ->where('members', [
                ['id' => $anne->id, 'name' => 'Anne', 'books_count' => 0, 'tools_count' => 0, 'own' => false],
                ['id' => $viewer->id, 'name' => 'Marie', 'books_count' => 1, 'tools_count' => 0, 'own' => true],
                ['id' => $paul->id, 'name' => 'Paul', 'books_count' => 3, 'tools_count' => 2, 'own' => false],
            ]));
})->with(['user' => [false], 'admin' => [true]]);

it('orders two members of the same name by id', function () {
    $first = User::factory()->create(['name' => 'Paul']);
    $second = User::factory()->create(['name' => 'Paul']);
    actingAs($second);

    get(route('family.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.0.id', $first->id)
            ->where('members.1.id', $second->id));
});

it('loads the list with the same number of queries whatever the number of members', function () {
    $countQueries = function (int $members): int {
        User::factory()->count($members)->create()->each(function (User $member) {
            Book::factory()->for($member)->count(2)->create();
            Tool::factory()->for($member)->count(2)->create();
        });
        actingAs(User::factory()->create());

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        get(route('family.index'))->assertOk();

        return $queries;
    };

    $withOneMember = $countQueries(1);
    expect($countQueries(5))->toBe($withOneMember);
});

it('leaves out the accounts that are not activated until their password is set', function () {
    $viewer = User::factory()->create(['name' => 'Marie']);
    $invited = User::factory()->invited()->create(['name' => 'Invité']);
    Book::factory()->for($invited)->create();
    actingAs($viewer);

    get(route('family.index'))
        ->assertInertia(fn (Assert $page) => $page->has('members', 1)->where('members.0.id', $viewer->id));

    $invited->forceFill(['password_set_at' => now()])->save();

    get(route('family.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 2)
            ->where('members.0.id', $invited->id)
            ->where('members.0.books_count', 1));
});
