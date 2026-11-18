<?php

return [
    'timeout' => (int) env('FYDA_TIMEOUT', 10),
    'oidc' => [
        'client_id' => env('FYDA_OIDC_CLIENT_ID'),
        'authorization_endpoint' => env('FYDA_OIDC_AUTHORIZATION_ENDPOINT'),
        'token_endpoint' => env('FYDA_OIDC_TOKEN_ENDPOINT'),
        'userinfo_endpoint' => env('FYDA_OIDC_USERINFO_ENDPOINT'),
        'redirect_uri' => env('FYDA_OIDC_REDIRECT_URI', env('APP_URL').'/api/v1/auth/fyda/callback'),
        'private_key_base64' => env('FYDA_OIDC_PRIVATE_KEY_BASE64'),
        'client_assertion_type' => env('FYDA_OIDC_CLIENT_ASSERTION_TYPE', 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer'),
        'client_assertion_ttl_seconds' => (int) env('FYDA_OIDC_CLIENT_ASSERTION_TTL_SECONDS', 900),
        'scope' => env('FYDA_OIDC_SCOPE', 'openid profile email'),
        'acr_values' => env('FYDA_OIDC_ACR_VALUES'),
        'claims_locales' => env('FYDA_OIDC_CLAIMS_LOCALES', 'en'),
        'pending_ttl_seconds' => (int) env('FYDA_OIDC_PENDING_TTL_SECONDS', 600),
        'result_ttl_seconds' => (int) env('FYDA_OIDC_RESULT_TTL_SECONDS', 300),
        'success_redirect_url' => env('FYDA_OIDC_SUCCESS_REDIRECT_URL'),
        'failure_redirect_url' => env('FYDA_OIDC_FAILURE_REDIRECT_URL'),
    ],
];
