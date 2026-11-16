<?php

return [

    /*
    | First administrator account, created by Database\Seeders\AdminUserSeeder.
    | The email and password are required; the seeder fails without them.
    */
    /*
    | Number of days the "set your password" link emailed to a new member
    | stays valid. The forgotten password link keeps its own 60 minutes
    | (config/auth.php).
    */
    'invitation_expire_days' => (int) env('FAMAPP_INVITATION_EXPIRE_DAYS', 7),

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrateur'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
