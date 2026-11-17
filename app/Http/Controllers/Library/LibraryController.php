<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Entry point of the Library module (sidebar): opens the bookshelves for now;
 * the books (F06) will live next to them under /library.
 */
class LibraryController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return to_route('library.bookshelves.index');
    }
}
