<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PSRestful API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PSRestful API integration
    |
    */

    'api_key' => env('PSRESTFUL_API_KEY'),
    'api_url' => env('PSRESTFUL_API_URL', 'https://api.psrestful.com'),
    'timeout' => 30,
];
