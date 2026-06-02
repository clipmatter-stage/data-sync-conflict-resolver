<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Akeneo PIM API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Akeneo PIM REST API integration
    | Documentation: https://api.akeneo.com/api-reference-index.html
    |
    */

    // API Base URL (your Akeneo PIM instance)
    'api_url' => env('AKENEO_API_URL', 'https://your-pim.cloud.akeneo.com'),

    // OAuth2 Client Credentials
    'client_id' => env('AKENEO_CLIENT_ID'),
    'client_secret' => env('AKENEO_CLIENT_SECRET'),

    // API User Credentials (automatically created with connection)
    'username' => env('AKENEO_USERNAME'),
    'password' => env('AKENEO_PASSWORD'),

    // Request timeout in seconds
    'timeout' => env('AKENEO_TIMEOUT', 30),

    // Token cache settings
    'token_cache_key' => 'akeneo_access_token',
    'token_cache_ttl' => 3500, // 3500 seconds (slightly less than 1 hour)

    // Pagination
    'default_page_size' => env('AKENEO_PAGE_SIZE', 100),
    'max_page_size' => 100, // Akeneo limit

    // Product endpoints preference (uuid recommended by Akeneo)
    'use_uuid_endpoint' => env('AKENEO_USE_UUID', true),
];
