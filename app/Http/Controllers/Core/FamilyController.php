<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Resources\Core\FamilyMemberResource;
use App\Services\Core\FamilyService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « La famille »: every member, with the numbers of their books and tools.
 */
class FamilyController extends Controller
{
    public function __construct(private readonly FamilyService $family) {}

    public function __invoke(): Response
    {
        return Inertia::render('Core/Family/Index', [
            'members' => FamilyMemberResource::collection($this->family->members())->resolve(),
        ]);
    }
}
