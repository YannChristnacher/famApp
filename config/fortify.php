<?php

use Laravel\Fortify\Features;

return [

    /*
    | famApp only uses Fortify for login, logout and the forgotten password flow.
    | Registration, email verification, two-factor authentication, passkeys and
    | profile or password updates are deliberately disabled.
    */

    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => false,

    /*
    | The login, forgot and reset password pages are Inertia pages rendered by
    | App\Http\Controllers\Auth\AuthPageController (see routes/web.php).
    */
    'views' => false,

    'home' => '/',

    'prefix' => '',

    'domain' => null,

    /*
    | Null keeps Fortify's built-in lockout: five failed attempts per email and
    | IP address, answered with the French "auth.throttle" validation message.
    */
    'limiters' => [
        'login' => null,
    ],

    'redirects' => [
        'login' => null,
        'logout' => null,
        'password-reset' => '/login',
    ],

    'features' => [
        Features::resetPasswords(),
    ],

];
