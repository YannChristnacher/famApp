<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Core/Home', [
            'appName' => config('app.name'),
        ]);
    }
}
