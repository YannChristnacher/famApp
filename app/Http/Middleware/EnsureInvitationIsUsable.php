<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invitation link works only while its signature is valid (not expired,
 * not altered) and the member has not chosen a password yet, which makes it
 * single use. Otherwise a French page explains what to do instead.
 */
class EnsureInvitationIsUsable
{
    public const EXPIRED = 'Ce lien pour définir votre mot de passe a expiré ou n\'est pas valide. Utilisez « Mot de passe oublié » pour en recevoir un nouveau.';

    public const ALREADY_USED = 'Votre mot de passe a déjà été défini. Connectez-vous, ou utilisez « Mot de passe oublié » si vous l\'avez oublié.';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->refuse($request, self::EXPIRED);
        }

        $user = $request->route('user');
        if ($user instanceof User && $user->hasSetPassword()) {
            return $this->refuse($request, self::ALREADY_USED);
        }

        return $next($request);
    }

    private function refuse(Request $request, string $message): Response
    {
        return Inertia::render('Auth/InvitationInvalid', ['message' => $message])
            ->toResponse($request)
            ->setStatusCode(Response::HTTP_FORBIDDEN);
    }
}
