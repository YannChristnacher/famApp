<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\ListBooksRequest;
use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Http\Resources\Core\MemberResource;
use App\Http\Resources\Library\BookResource;
use App\Http\Resources\Library\BookshelfResource;
use App\Models\Library\Book;
use App\Models\User;
use App\Services\Library\BookService;
use App\Services\Library\BookshelfService;
use App\Services\Library\SavedBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The books of the signed-in member ("Mes livres"); the page of a book is
 * readable by the whole family.
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
        $saved = $this->books->create(
            $user,
            $request->bookAttributes(),
            $request->bookshelfIds(),
            $request->coverInput(),
            $request->googleVolumeId(),
        );

        return $this->toBook($saved, 'ajouté');
    }

    /**
     * Readable by every member (F11); `editable` shows the actions to the owner only.
     */
    public function show(Book $book): Response
    {
        Gate::authorize('view', $book);

        return Inertia::render('Library/Books/Show', [
            'book' => (new BookResource($book->load('bookshelves')))->resolve(),
            'owner' => (new MemberResource($book->user))->resolve(),
            'editable' => Gate::allows('update', $book),
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
        $saved = $this->books->update(
            $book,
            $request->bookAttributes(),
            $request->bookshelfIds(),
            $request->coverInput(),
            $request->googleVolumeId(),
        );

        return $this->toBook($saved, 'modifié');
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

    /**
     * @param  'ajouté'|'modifié'  $action
     */
    private function toBook(SavedBook $saved, string $action): RedirectResponse
    {
        $message = "Le livre « {$saved->book->title} » a été {$action}";
        $message .= $saved->googleCoverMissing ? ', mais la couverture Google Books n’a pas pu être récupérée.' : '.';
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('library.books.show', $saved->book);
    }
}
