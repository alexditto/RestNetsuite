<?php

declare(strict_types=1);

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\NetSuiteConfig;

return [
    'account_id' => env('NETSUITE_ACCOUNT_ID'),
    'auth_method' => env('NETSUITE_AUTH_METHOD', AuthMethod::TokenBasedAuth->value),
    'environment' => env('NETSUITE_ENVIRONMENT', 'sandbox'),
    'scopes' => ['restlets', 'rest_webservices'],
    'max_attempts' => env('NETSUITE_MAX_ATTEMPTS', NetSuiteConfig::DEFAULT_MAX_ATTEMPTS),

    // Logs every REST request/response pair to $path as {id}.request.txt / {id}.response.txt.
    // Off by default — meant for local debugging, not production traffic.
    'logging' => [
        'enabled' => env('NETSUITE_LOG_REQUESTS', false),
        'path' => env('NETSUITE_LOG_PATH', storage_path('logs/netsuite')),
    ],

    // Used when auth_method is 'oauth2' (OAuth 2.0 Client Credentials / M2M).
    'oauth2' => [
        'client_id' => env('NETSUITE_CLIENT_ID'),
        'certificate_id' => env('NETSUITE_CERTIFICATE_ID'),
        'private_key_path' => env('NETSUITE_PRIVATE_KEY_PATH'),
    ],

    // Used when auth_method is 'tba' (Token-Based Authentication).
    'tba' => [
        'consumer_key' => env('NETSUITE_CONSUMER_KEY'),
        'consumer_secret' => env('NETSUITE_CONSUMER_SECRET'),
        'token_id' => env('NETSUITE_TOKEN_ID'),
        'token_secret' => env('NETSUITE_TOKEN_SECRET'),
        'hash_algorithm' => env('NETSUITE_HASH_TYPE', 'sha256'),
    ],
];
