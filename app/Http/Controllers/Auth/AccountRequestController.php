<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAccountRequestRequest;
use App\Jobs\Auth\SubmitAccountRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountRequestController extends Controller
{
    /**
     * Same message whatever happens to the request (see SubmitAccountRequest).
     */
    public const SENT_STATUS = 'Votre demande a bien été envoyée. Un administrateur vous contactera par email.';

    public function create(): Response
    {
        return Inertia::render('Auth/RequestAccount');
    }

    public function store(StoreAccountRequestRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, message?: string|null} $data */
        $data = $request->validated();
        SubmitAccountRequest::dispatch($data);

        return to_route('login')->with('status', self::SENT_STATUS);
    }
}
