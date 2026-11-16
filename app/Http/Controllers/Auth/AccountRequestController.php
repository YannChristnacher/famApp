<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAccountRequestRequest;
use App\Services\Auth\AccountRequestService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountRequestController extends Controller
{
    /**
     * Same message whatever happened to the request (see AccountRequestService).
     */
    public const SENT_STATUS = 'Votre demande a bien été envoyée. Un administrateur vous contactera par email.';

    public function create(): Response
    {
        return Inertia::render('Auth/RequestAccount');
    }

    public function store(StoreAccountRequestRequest $request, AccountRequestService $accountRequests): RedirectResponse
    {
        /** @var array{name: string, email: string, message?: string|null} $data */
        $data = $request->validated();
        $accountRequests->submit($data);

        return to_route('login')->with('status', self::SENT_STATUS);
    }
}
