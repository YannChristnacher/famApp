<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Entry point of the Library module (sidebar): opens the books of the member;
 * their bookshelves are one click away.
 */
class LibraryController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return to_route('library.books.index');
    }
}
