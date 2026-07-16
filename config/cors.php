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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Comma-separated origins in CORS_ALLOWED_ORIGINS, e.g.
     * "https://app.spendlog.test,https://staging.spendlog.test".
     *
     * Defaults to '*' because this API authenticates with bearer tokens rather
     * than cookies, so a wildcard does not expose an ambient-credential CSRF
     * path. Pin it to the real client origins in production anyway — '*' also
     * lets any site read responses if a token ever leaks into a browser.
     */
    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
