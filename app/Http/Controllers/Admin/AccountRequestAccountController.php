<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountFromRequestRequest;
use App\Models\Auth\AccountRequest;
use App\Services\Admin\AccountRequestReviewService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Creation of a member account from a pending account request.
 */
class AccountRequestAccountController extends Controller
{
    public function __construct(private readonly AccountRequestReviewService $reviews) {}

    public function create(AccountRequest $accountRequest): Response
    {
        $this->reviews->ensurePending($accountRequest);

        return Inertia::render('Admin/AccountRequests/CreateAccount', [
            'accountRequest' => $accountRequest->only(['id', 'name', 'email', 'message']),
            'roles' => Role::assignableValues(),
        ]);
    }

    public function store(StoreAccountFromRequestRequest $request, AccountRequest $accountRequest): RedirectResponse
    {
        /** @var array{name: string, email: string, role: string} $data */
        $data = $request->validated();
        $user = $this->reviews->createAccount($accountRequest, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Le compte de {$user->name} a été créé. Un email lui a été envoyé pour définir son mot de passe.",
        ]);

        return to_route('admin.account-requests.index');
    }
}
