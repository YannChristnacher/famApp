<?php

return [

    /*
    | The route() helper comes from the ziggy-js npm package bundled by Vite,
    | so the Blade directive only has to print the route list.
    */
    'skip-route-function' => true,

    /*
    | Framework-internal routes are never needed by the front end.
    */
    'except' => ['storage.*'],

];
