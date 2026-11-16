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

// Printed only to administrators, on top of the signed-in routes.
$adminRoutes = [
    'admin.account-requests.index',
    'admin.account-requests.account.create',
    'admin.account-requests.account.store',
    'admin.account-requests.reject',
];

return [

    /*
    | The route() helper comes from the ziggy-js npm package bundled by Vite,
    | so the Blade directive only has to print the route list.
    */
    'skip-route-function' => true,

    /*
    | Default allowlist of the named routes the front end may use (printed when
    | no group is given). Any route used by the front end must be added to a
    | group explicitly; the admin routes are never part of this list.
    */
    'only' => [...$guestRoutes, ...$authenticatedRoutes],

    /*
    | The allowlist is split by authentication state and role (App\Support\ZiggyGroup):
    | app.blade.php prints the "guest" group to visitors, the "auth" group to
    | signed-in users, and the "auth" and "admin" groups to administrators.
    */
    'groups' => [
        'guest' => $guestRoutes,
        'auth' => $authenticatedRoutes,
        'admin' => $adminRoutes,
    ],

];
