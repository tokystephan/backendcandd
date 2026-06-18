<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['accept', 'accept-language', 'authorization', 'content-language', 'content-type', 'X-requested-with', 'origin', 'X-csrf-token', 'X-xsrf-token', 'withcredentials'],

    'exposed_headers' => ['cache-control', 'content-language', 'content-type', 'Last-Modified', 'Pragma', 'Expires', 'Authorization'],


    'max_age' =>  86400,// 24 hours

    'supports_credentials' => true,

];
