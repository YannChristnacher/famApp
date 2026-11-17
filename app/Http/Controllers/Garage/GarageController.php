<?php

namespace App\Http\Controllers\Garage;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Entry point of the Garage module (sidebar): opens the shelves of the member
 * until their tools exist (F10).
 */
class GarageController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return to_route('garage.shelves.index');
    }
}
