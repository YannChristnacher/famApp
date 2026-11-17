<?php

namespace App\Http\Controllers\Garage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Garage\MoveToolShelfRequest;
use App\Models\Garage\ToolShelf;
use App\Services\Garage\ToolShelfService;
use Illuminate\Http\RedirectResponse;

/**
 * Moves a tool shelf one place up or down in the garage of its owner.
 */
class ToolShelfPositionController extends Controller
{
    public function __construct(private readonly ToolShelfService $shelves) {}

    public function update(MoveToolShelfRequest $request, ToolShelf $shelf): RedirectResponse
    {
        $this->shelves->move($shelf, $request->direction());

        return to_route('garage.shelves.index');
    }
}
