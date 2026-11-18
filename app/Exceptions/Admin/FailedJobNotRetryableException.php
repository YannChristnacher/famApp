<?php

namespace App\Exceptions\Admin;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

/**
 * A failed job whose payload cannot be read again (not JSON, corrupted or
 * undecryptable command): it is not pushed back and stays in failed_jobs.
 */
class FailedJobNotRetryableException extends RuntimeException
{
    public const MESSAGE = 'Ce job ne peut pas être relancé : son contenu est illisible. Il reste dans la liste des jobs en échec.';

    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }

    /**
     * Nothing more to log: the cause is reported by FailedJobService.
     */
    public function report(): void {}

    public function render(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => self::MESSAGE]);

        return to_route('admin.control');
    }
}
