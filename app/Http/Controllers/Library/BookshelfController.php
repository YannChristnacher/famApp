<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreBookshelfRequest;
use App\Http\Requests\Library\UpdateBookshelfRequest;
use App\Http\Resources\Library\BookshelfResource;
use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Services\Library\BookshelfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bookshelves of the signed-in member ("Ma bibliothèque").
 */
class BookshelfController extends Controller
{
    public function __construct(private readonly BookshelfService $bookshelves) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Library/Bookshelves/Index', [
            'bookshelves' => BookshelfResource::collection($this->bookshelves->listFor($user))->resolve(),
        ]);
    }

    public function store(StoreBookshelfRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $bookshelf = $this->bookshelves->create($user, $request->string('name')->value());

        return $this->backToList("L'étagère « {$bookshelf->name} » a été créée.");
    }

    public function update(UpdateBookshelfRequest $request, Bookshelf $bookshelf): RedirectResponse
    {
        $this->bookshelves->rename($bookshelf, $request->string('name')->value());

        return $this->backToList("L'étagère a été renommée en « {$bookshelf->name} ».");
    }

    public function destroy(Bookshelf $bookshelf): RedirectResponse
    {
        Gate::authorize('delete', $bookshelf);
        $this->bookshelves->delete($bookshelf);

        return $this->backToList("L'étagère « {$bookshelf->name} » a été supprimée.");
    }

    private function backToList(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('library.bookshelves.index');
    }
}
