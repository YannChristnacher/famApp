<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google Books, called by the server only (App\Services\Library\GoogleBooksService).
    | The API key is optional: without it, Google applies its anonymous quota.
    | Cover images are downloaded only over https from the hosts listed here.
    */
    'google_books' => [
        'key' => env('GOOGLE_BOOKS_API_KEY'),
        'base_url' => env('GOOGLE_BOOKS_BASE_URL', 'https://www.googleapis.com/books/v1'),
        'timeout' => 5,
        'cache_minutes' => 30,
        // Calls made while saving books (volume confirmation, cover download), per member.
        'save_calls_per_minute' => 20,
        'cover_hosts' => ['books.google.com', 'books.googleusercontent.com'],
        'cover_max_kilobytes' => 2048,
        'cover_timeout' => 5,
        // Days the journal of the calls (google_books_requests) is kept; older
        // lines are deleted by the daily model:prune task.
        'journal_retention_days' => (int) env('GOOGLE_BOOKS_JOURNAL_RETENTION_DAYS', 90),
    ],

];
