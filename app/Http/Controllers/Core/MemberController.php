<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ShowMemberRequest;
use App\Http\Resources\Core\MemberResource;
use App\Http\Resources\Garage\ToolResource;
use App\Http\Resources\Garage\ToolShelfResource;
use App\Http\Resources\Library\BookResource;
use App\Http\Resources\Library\BookshelfResource;
use App\Models\User;
use App\Services\Garage\ToolService;
use App\Services\Garage\ToolShelfService;
use App\Services\Library\BookService;
use App\Services\Library\BookshelfService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page of a member: their library and their garage, read-only for
 * everyone (the owner changes them from « Mes livres » and « Mes outils »).
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly BookService $books,
        private readonly BookshelfService $bookshelves,
        private readonly ToolService $tools,
        private readonly ToolShelfService $toolShelves,
    ) {}

    public function __invoke(ShowMemberRequest $request, User $member): Response
    {
        $bookshelf = $request->bookshelfFilter();
        $shelf = $request->shelfFilter();

        return Inertia::render('Core/Family/Show', [
            'member' => (new MemberResource($member))->resolve(),
            'own' => $member->is($request->user()),
            'tab' => $request->tab(),
            'bookshelves' => BookshelfResource::collection($this->bookshelves->listFor($member))->resolve(),
            'books' => BookResource::collection($this->books->listFor($member, $bookshelf))->resolve(),
            'toolShelves' => ToolShelfResource::collection($this->toolShelves->listFor($member))->resolve(),
            'tools' => ToolResource::collection($this->tools->listFor($member, $shelf))->resolve(),
            'filters' => ['bookshelf' => $bookshelf?->id, 'shelf' => $shelf?->id],
        ]);
    }
}
