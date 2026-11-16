<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureInvitationIsUsable;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use App\Services\Auth\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Set my password" page reached from the welcome email (signed link).
 */
class InvitationController extends Controller
{
    public const PASSWORD_DEFINED = 'Votre mot de passe est défini. Vous pouvez maintenant vous connecter.';

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Auth/SetPassword', [
            'name' => $user->name,
            'email' => $user->email,
            // The form posts back to the same signed url.
            'submitUrl' => $request->getRequestUri(),
        ]);
    }

    public function store(SetPasswordRequest $request, User $user, InvitationService $invitations): RedirectResponse
    {
        if (! $invitations->setFirstPassword($user, $request->string('password')->toString())) {
            return back()->withErrors(['password' => EnsureInvitationIsUsable::ALREADY_USED]);
        }

        return to_route('login')->with('status', self::PASSWORD_DEFINED);
    }
}
