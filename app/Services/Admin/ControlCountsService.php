<?php

namespace App\Services\Admin;

use App\Models\Auth\AccountRequest;
use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;

/**
 * Figures of the admin control page: one count query each.
 */
class ControlCountsService
{
    public function pendingAccountRequests(): int
    {
        return AccountRequest::query()->pending()->count();
    }

    /**
     * @return array{members: int, invited: int, books: int, tools: int, bookshelves: int, toolShelves: int, brands: int}
     */
    public function counts(): array
    {
        return [
            'members' => User::query()->activated()->count(),
            'invited' => User::query()->whereNull('password_set_at')->count(),
            'books' => Book::query()->count(),
            'tools' => Tool::query()->count(),
            'bookshelves' => Bookshelf::query()->count(),
            'toolShelves' => ToolShelf::query()->count(),
            'brands' => Brand::query()->count(),
        ];
    }
}
