<?php

$guestRoutes = [
    'login',
    'login.store',
    'password.request',
    'password.email',
    'password.update',
    'account-request.create',
    'account-request.store',
];

$authenticatedRoutes = [
    'home',
    'logout',
];

return [

    /*
    | The route() helper comes from the ziggy-js npm package bundled by Vite,
    | so the Blade directive only has to print the route list.
    */
    'skip-route-function' => true,

    /*
    | Allowlist of the named routes the front end may use. Any route used by the
    | front end must be added here explicitly; never expose admin routes.
    */
    'only' => [...$guestRoutes, ...$authenticatedRoutes],

    /*
    | The allowlist is split by authentication state: app.blade.php prints the
    | "guest" group to visitors and the "auth" group to signed-in users.
    */
    'groups' => [
        'guest' => $guestRoutes,
        'auth' => $authenticatedRoutes,
    ],

];
