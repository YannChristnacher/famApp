<?php

namespace App\Http\Controllers\Garage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Garage\ListToolsRequest;
use App\Http\Requests\Garage\StoreToolRequest;
use App\Http\Requests\Garage\UpdateToolRequest;
use App\Http\Resources\Garage\BrandResource;
use App\Http\Resources\Garage\ToolDetailsResource;
use App\Http\Resources\Garage\ToolResource;
use App\Http\Resources\Garage\ToolShelfResource;
use App\Models\Garage\Tool;
use App\Models\User;
use App\Services\Garage\BrandService;
use App\Services\Garage\ToolService;
use App\Services\Garage\ToolShelfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tools of the signed-in member ("Mes outils").
 */
class ToolController extends Controller
{
    private const DETAILS = ['brand', 'shelves', 'thumbnail', 'photos', 'accessories'];

    public function __construct(
        private readonly ToolService $tools,
        private readonly ToolShelfService $shelves,
        private readonly BrandService $brands,
    ) {}

    public function index(ListToolsRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $shelf = $request->shelfFilter();
        $brand = $request->brandFilter();

        return Inertia::render('Garage/Tools/Index', [
            'tools' => ToolResource::collection($this->tools->listFor($user, $shelf, $brand))->resolve(),
            'shelves' => $this->shelvesOf($user),
            'brands' => BrandResource::collection($this->tools->brandsOf($user))->resolve(),
            'filters' => ['shelf' => $shelf?->id, 'brand' => $brand?->id],
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Garage/Tools/Create', [
            'shelves' => $this->shelvesOf($user),
            'brands' => $this->allBrands(),
        ]);
    }

    public function store(StoreToolRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tool = $this->tools->create($user, $request->toolInput());

        return $this->toTool($tool, 'ajouté');
    }

    public function show(Tool $tool): Response
    {
        Gate::authorize('view', $tool);

        return Inertia::render('Garage/Tools/Show', [
            'tool' => (new ToolDetailsResource($tool->load(self::DETAILS)))->resolve(),
        ]);
    }

    public function edit(Request $request, Tool $tool): Response
    {
        Gate::authorize('update', $tool);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Garage/Tools/Edit', [
            'tool' => (new ToolDetailsResource($tool->load(self::DETAILS)))->resolve(),
            'shelves' => $this->shelvesOf($user),
            'brands' => $this->allBrands(),
        ]);
    }

    public function update(UpdateToolRequest $request, Tool $tool): RedirectResponse
    {
        return $this->toTool($this->tools->update($tool, $request->toolInput()), 'modifié');
    }

    public function destroy(Tool $tool): RedirectResponse
    {
        Gate::authorize('delete', $tool);
        $this->tools->delete($tool);

        Inertia::flash('toast', ['type' => 'success', 'message' => "L’outil « {$tool->name} » a été supprimé."]);

        return to_route('garage.tools.index');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shelvesOf(User $user): array
    {
        return ToolShelfResource::collection($this->shelves->listFor($user))->resolve();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allBrands(): array
    {
        return BrandResource::collection($this->brands->all())->resolve();
    }

    /**
     * @param  'ajouté'|'modifié'  $action
     */
    private function toTool(Tool $tool, string $action): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => "L’outil « {$tool->name} » a été {$action}."]);

        return to_route('garage.tools.show', $tool);
    }
}
