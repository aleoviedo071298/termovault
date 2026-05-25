<?php

return [
    'region' => env('COGNITO_REGION', ''),
    'user_pool_id' => env('COGNITO_USER_POOL_ID', ''),
    'app_client_id' => env('COGNITO_APP_CLIENT_ID', ''),
    'required' => env('COGNITO_AUTH_REQUIRED', false),
    'leeway' => (int) env('COGNITO_JWT_LEEWAY', 60),
];
