<?php

namespace App\Http\Controllers\Library;

use App\Exceptions\Library\GoogleBooksUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\LookupGoogleBooksRequest;
use App\Http\Resources\Library\GoogleBooksLookupResource;
use App\Models\User;
use App\Services\Library\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prefill of the book form from the ISBN (JSON, called by BookForm.vue).
 */
class GoogleBooksLookupController extends Controller
{
    public const NOT_FOUND = 'Aucun livre trouvé pour cet ISBN. Vous pouvez le saisir à la main.';

    public function __invoke(LookupGoogleBooksRequest $request, GoogleBooksService $googleBooks): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $volume = $googleBooks->findByIsbn($request->isbn(), $user);
        } catch (GoogleBooksUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($volume === null) {
            return response()->json(['message' => self::NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        return (new GoogleBooksLookupResource($volume))->response();
    }
}
