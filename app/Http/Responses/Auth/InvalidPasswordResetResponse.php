<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same error for an unknown email and for an invalid or expired token: the
 * reset form cannot be used to find out which emails have an account.
 */
class InvalidPasswordResetResponse implements FailedPasswordResetResponse
{
    public const ERROR = 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.';

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::ERROR]);
    }
}
