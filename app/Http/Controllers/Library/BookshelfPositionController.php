<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\MoveBookshelfRequest;
use App\Models\Library\Bookshelf;
use App\Services\Library\BookshelfService;
use Illuminate\Http\RedirectResponse;

/**
 * Moves a bookshelf one place up or down in the library of its owner.
 */
class BookshelfPositionController extends Controller
{
    public function __construct(private readonly BookshelfService $bookshelves) {}

    public function update(MoveBookshelfRequest $request, Bookshelf $bookshelf): RedirectResponse
    {
        $this->bookshelves->move($bookshelf, $request->direction());

        return to_route('library.bookshelves.index');
    }
}
