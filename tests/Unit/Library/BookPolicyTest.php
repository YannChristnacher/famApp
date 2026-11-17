<?php

use App\Models\Library\Book;
use App\Models\User;
use App\Policies\Library\BookPolicy;

function bookUserWithId(int $id, bool $admin = false): User
{
    $user = $admin ? User::factory()->admin()->make() : User::factory()->make();
    $user->id = $id;

    return $user;
}

it('lets only the owner see and change a book, and hides it from anyone else', function (string $ability) {
    $book = Book::factory()->make(['user_id' => 1]);
    $policy = new BookPolicy;

    expect($policy->{$ability}(bookUserWithId(1), $book)->allowed())->toBeTrue();

    $denied = $policy->{$ability}(bookUserWithId(2), $book);
    expect($denied->denied())->toBeTrue()
        ->and($denied->status())->toBe(404);

    $deniedToAdmin = $policy->{$ability}(bookUserWithId(3, admin: true), $book);
    expect($deniedToAdmin->denied())->toBeTrue()
        ->and($deniedToAdmin->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('is the policy of the book model', function () {
    expect(Gate::getPolicyFor(Book::class))->toBeInstanceOf(BookPolicy::class);
});
