<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\ListBooksRequest;
use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Http\Resources\Library\BookResource;
use App\Http\Resources\Library\BookshelfResource;
use App\Models\Library\Book;
use App\Models\User;
use App\Services\Library\BookService;
use App\Services\Library\BookshelfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The books of the signed-in member ("Mes livres").
 */
class BookController extends Controller
{
    public function __construct(
        private readonly BookService $books,
        private readonly BookshelfService $bookshelves,
    ) {}

    public function index(ListBooksRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = $request->bookshelfFilter();

        return Inertia::render('Library/Books/Index', [
            'books' => BookResource::collection($this->books->listFor($user, $filter))->resolve(),
            'bookshelves' => $this->bookshelvesOf($user),
            'filter' => $filter?->id,
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Library/Books/Create', [
            'bookshelves' => $this->bookshelvesOf($user),
        ]);
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $book = $this->books->create($user, $request->bookAttributes(), $request->bookshelfIds(), $request->cover());

        return $this->toBook($book, "Le livre « {$book->title} » a été ajouté.");
    }

    public function show(Book $book): Response
    {
        Gate::authorize('view', $book);

        return Inertia::render('Library/Books/Show', [
            'book' => (new BookResource($book->load('bookshelves')))->resolve(),
        ]);
    }

    public function edit(Request $request, Book $book): Response
    {
        Gate::authorize('update', $book);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Library/Books/Edit', [
            'book' => (new BookResource($book->load('bookshelves')))->resolve(),
            'bookshelves' => $this->bookshelvesOf($user),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->books->update(
            $book,
            $request->bookAttributes(),
            $request->bookshelfIds(),
            $request->cover(),
            $request->removesCover(),
        );

        return $this->toBook($book, "Le livre « {$book->title} » a été modifié.");
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);
        $this->books->delete($book);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Le livre « {$book->title} » a été supprimé."]);

        return to_route('library.books.index');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookshelvesOf(User $user): array
    {
        return BookshelfResource::collection($this->bookshelves->listFor($user))->resolve();
    }

    private function toBook(Book $book, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('library.books.show', $book);
    }
}
