<?php

return [

    /*
    | The route() helper comes from the ziggy-js npm package bundled by Vite,
    | so the Blade directive only has to print the route list.
    */
    'skip-route-function' => true,

    /*
    | Allowlist of the named routes printed in every page by @routes, guests
    | included. Any route used by the front end must be added here explicitly;
    | never expose admin routes to pages visitors can reach.
    */
    'only' => [
        'home',
    ],

];
