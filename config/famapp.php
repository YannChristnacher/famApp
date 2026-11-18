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

    /*
    | Admin control page (F13): number of lines of each list, and the bounded
    | reading of the Laravel log for the latest errors (the most recently
    | written laravel*.log file of the directory, read from its end only).
    */
    'control' => [
        'recent_limit' => 10,
        'log_directory' => storage_path('logs'),
        'log_tail_bytes' => 256 * 1024,
        'message_max_length' => 300,
    ],

];
