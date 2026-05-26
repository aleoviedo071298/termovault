<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $region = (string) config('cognito.region');
        $clientId = (string) config('cognito.app_client_id');
        $clientSecret = (string) config('cognito.app_client_secret');

        if ($region === '' || $clientId === '') {
            return response()->json([
                'message' => 'Cognito is not configured on the server.',
            ], 500);
        }

        $authParams = [
            'USERNAME' => $credentials['email'],
            'PASSWORD' => $credentials['password'],
        ];

        if ($clientSecret !== '') {
            $message = $credentials['email'] . $clientId;
            $hash = hash_hmac('sha256', $message, $clientSecret, true);
            $authParams['SECRET_HASH'] = base64_encode($hash);
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-amz-json-1.1',
            'X-Amz-Target' => 'AWSCognitoIdentityProviderService.InitiateAuth',
        ])->post("https://cognito-idp.{$region}.amazonaws.com/", [
            'AuthFlow' => 'USER_PASSWORD_AUTH',
            'ClientId' => $clientId,
            'AuthParameters' => $authParams,
        ]);

        $data = $response->json();

        if (! $response->successful()) {
            $message = $data['message'] ?? 'Authentication failed';
            return response()->json([
                'message' => $message,
                'error' => $data['__type'] ?? 'UnknownError',
            ], $response->status() >= 400 && $response->status() < 500 ? $response->status() : 401);
        }

        if (isset($data['ChallengeName'])) {
            return response()->json([
                'message' => 'Challenge required: ' . $data['ChallengeName'],
                'challenge' => $data['ChallengeName'],
                'session' => $data['Session'] ?? null,
            ], 202);
        }

        $authResult = $data['AuthenticationResult'] ?? [];

        return response()->json([
            'access_token' => $authResult['AccessToken'] ?? null,
            'id_token' => $authResult['IdToken'] ?? null,
            'refresh_token' => $authResult['RefreshToken'] ?? null,
            'expires_in' => $authResult['ExpiresIn'] ?? null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('auth.claims', []);
        $empresaId = $request->attributes->get('auth.empresa_id');
        $userId = $request->attributes->get('auth.user_id');

        return response()->json([
            'id' => $userId,
            'sub' => $claims['sub'] ?? null,
            'email' => $claims['email'] ?? null,
            'username' => $claims['username'] ?? ($claims['cognito:username'] ?? null),
            'token_use' => $claims['token_use'] ?? null,
            'empresa_id' => $empresaId,
            'claims' => $claims,
        ]);
    }
}
