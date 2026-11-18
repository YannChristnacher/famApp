<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\SearchRequest;
use App\Http\Resources\Core\SearchGroupResource;
use App\Models\User;
use App\Services\Core\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * Global search of the header (JSON, called by GlobalSearch.vue): the
 * results of every module, grouped.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'groups' => SearchGroupResource::collection($search->search($request->searchedText(), $user))->resolve(),
        ]);
    }
}
