<?php

namespace App\Services\Admin;

use App\Models\Garage\Tool;
use App\Models\Library\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Latest additions and latest sign-ins, for the admin control page.
 */
class RecentActivityService
{
    /**
     * The latest books and tools of every member, newest first.
     *
     * @return Collection<int, Book|Tool>
     */
    public function latestAdditions(int $limit): Collection
    {
        $books = Book::query()
            ->select(['id', 'user_id', 'title', 'created_at'])
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $tools = Tool::query()
            ->select(['id', 'user_id', 'name', 'created_at'])
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        /** @var Collection<int, Book|Tool> $additions */
        $additions = collect([...$books, ...$tools]);

        return $additions
            ->sortByDesc(fn (Book|Tool $addition) => [$addition->created_at?->getTimestamp() ?? 0, $addition->id])
            ->take($limit)
            ->values();
    }

    /**
     * Members who signed in, the most recent sign-in first.
     *
     * @return EloquentCollection<int, User>
     */
    public function latestLogins(int $limit): EloquentCollection
    {
        return User::query()
            ->select(['id', 'name', 'last_login_at'])
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
