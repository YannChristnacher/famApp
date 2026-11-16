<?php

return [

    /*
    | First administrator account, created by Database\Seeders\AdminUserSeeder.
    | The email and password are required; the seeder fails without them.
    */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrateur'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
