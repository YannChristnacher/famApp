<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same answer whether a link was sent, the email is unknown or a link was
 * requested too recently: nobody can find out which emails have an account.
 */
class PasswordResetLinkRequestedResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const STATUS = 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.';

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return back()->with('status', self::STATUS);
    }
}
