<?php

namespace App\Http\Controllers\Garage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Garage\StoreToolShelfRequest;
use App\Http\Requests\Garage\UpdateToolShelfRequest;
use App\Http\Resources\Garage\ToolShelfResource;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Services\Garage\ToolShelfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tool shelves of the signed-in member ("Mon garage").
 */
class ToolShelfController extends Controller
{
    public function __construct(private readonly ToolShelfService $shelves) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Garage/Shelves/Index', [
            'shelves' => ToolShelfResource::collection($this->shelves->listFor($user))->resolve(),
        ]);
    }

    public function store(StoreToolShelfRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $shelf = $this->shelves->create($user, $request->string('name')->value());

        return $this->backToList("L'étagère « {$shelf->name} » a été créée.");
    }

    public function update(UpdateToolShelfRequest $request, ToolShelf $shelf): RedirectResponse
    {
        $this->shelves->rename($shelf, $request->string('name')->value());

        return $this->backToList("L'étagère a été renommée en « {$shelf->name} ».");
    }

    public function destroy(ToolShelf $shelf): RedirectResponse
    {
        Gate::authorize('delete', $shelf);
        $this->shelves->delete($shelf);

        return $this->backToList("L'étagère « {$shelf->name} » a été supprimée.");
    }

    private function backToList(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('garage.shelves.index');
    }
}
