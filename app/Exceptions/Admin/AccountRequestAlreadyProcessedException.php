<?php

namespace App\Exceptions\Admin;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

/**
 * An administrator tried to approve or reject a request that was already
 * approved or rejected (a second click, or another administrator was faster).
 */
class AccountRequestAlreadyProcessedException extends RuntimeException
{
    public const MESSAGE = 'Cette demande a déjà été traitée.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }

    public function render(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => self::MESSAGE]);

        return to_route('admin.account-requests.index');
    }
}
