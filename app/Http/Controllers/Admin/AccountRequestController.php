<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AccountRequestResource;
use App\Models\Auth\AccountRequest;
use App\Services\Admin\AccountRequestReviewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountRequestController extends Controller
{
    public function __construct(private readonly AccountRequestReviewService $reviews) {}

    public function index(): Response
    {
        return Inertia::render('Admin/AccountRequests/Index', [
            'accountRequests' => AccountRequestResource::collection($this->reviews->pendingFirst())->resolve(),
        ]);
    }

    public function reject(AccountRequest $accountRequest): RedirectResponse
    {
        $this->reviews->reject($accountRequest);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "La demande de {$accountRequest->name} a été refusée.",
        ]);

        return to_route('admin.account-requests.index');
    }
}
