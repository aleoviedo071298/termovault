<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$region = env('COGNITO_REGION');
$clientId = env('COGNITO_APP_CLIENT_ID');
$clientSecret = env('COGNITO_APP_CLIENT_SECRET');

if ($argc < 3) {
    echo "Usage: php auth_login.php <username/email> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

// Calculate SECRET_HASH
$message = $username . $clientId;
$hash = hash_hmac('sha256', $message, $clientSecret, true);
$secretHash = base64_encode($hash);

$payload = [
    'AuthFlow' => 'USER_PASSWORD_AUTH',
    'ClientId' => $clientId,
    'AuthParameters' => [
        'USERNAME' => $username,
        'PASSWORD' => $password,
        'SECRET_HASH' => $secretHash,
    ]
];

$url = "https://cognito-idp.{$region}.amazonaws.com/";

function sendCognitoRequest(string $url, string $target, array $payload): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-amz-json-1.1',
        "X-Amz-Target: AWSCognitoIdentityProviderService.{$target}"
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Curl error: " . curl_error($ch) . "\n";
        exit(1);
    }
    curl_close($ch);
    return json_decode($response, true);
}

$data = sendCognitoRequest($url, 'InitiateAuth', $payload);

if (isset($data['ChallengeName']) && $data['ChallengeName'] === 'NEW_PASSWORD_REQUIRED') {
    echo "Cognito status: FORCE_CHANGE_PASSWORD. Resolving challenge automatically...\n";
    
    $challengePayload = [
        'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
        'ClientId' => $clientId,
        'Session' => $data['Session'],
        'ChallengeResponses' => [
            'USERNAME' => $username,
            'NEW_PASSWORD' => $password,
            'SECRET_HASH' => $secretHash,
            'userAttributes.nickname' => 'ale'
        ]
    ];
    
    $data = sendCognitoRequest($url, 'RespondToAuthChallenge', $challengePayload);
}

if (isset($data['AuthenticationResult'])) {
    echo "Login successful!\n\n";
    echo "ID Token:\n" . $data['AuthenticationResult']['IdToken'] . "\n\n";
    echo "Access Token:\n" . $data['AuthenticationResult']['AccessToken'] . "\n\n";
} else {
    echo "Authentication failed:\n";
    print_r($data);
}
