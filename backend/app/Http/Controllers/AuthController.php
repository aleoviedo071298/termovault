<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => 'required|email',
            'password' => 'nullable|string',
            'session' => 'nullable|string',
            'new_password' => 'nullable|string|min:8',
            'nickname' => 'nullable|string|max:100',
        ]);

        $region = (string) config('cognito.region');
        $clientId = (string) config('cognito.app_client_id');
        $clientSecret = (string) config('cognito.app_client_secret');

        if ($region === '' || $clientId === '') {
            return response()->json([
                'message' => 'Cognito is not configured on the server.',
            ], 500);
        }

        $email = mb_strtolower(trim($payload['email']));

        if ($clientSecret !== '') {
            $message = $email . $clientId;
            $hash = hash_hmac('sha256', $message, $clientSecret, true);
            $secretHash = base64_encode($hash);
        } else {
            $secretHash = null;
        }

        if (! empty($payload['session']) && ! empty($payload['new_password'])) {
            $defaultNickname = Str::before($email, '@');
            $nickname = trim((string) ($payload['nickname'] ?? $defaultNickname));

            $challengeResponses = [
                'USERNAME' => $email,
                'NEW_PASSWORD' => $payload['new_password'],
                'userAttributes.nickname' => $nickname !== '' ? $nickname : $defaultNickname,
            ];
            if ($secretHash) {
                $challengeResponses['SECRET_HASH'] = $secretHash;
            }

            $challengeResponse = Http::withHeaders([
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AWSCognitoIdentityProviderService.RespondToAuthChallenge',
            ])->post("https://cognito-idp.{$region}.amazonaws.com/", [
                'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
                'ClientId' => $clientId,
                'Session' => $payload['session'],
                'ChallengeResponses' => $challengeResponses,
            ]);

            $challengeData = $challengeResponse->json();
            if (! $challengeResponse->successful()) {
                return response()->json([
                    'message' => $challengeData['message'] ?? 'No se pudo actualizar la contraseña inicial',
                    'error' => $challengeData['__type'] ?? 'UnknownError',
                ], 401);
            }

            $authResult = $challengeData['AuthenticationResult'] ?? [];
            if (($authResult['AccessToken'] ?? null) && ($authResult['IdToken'] ?? null)) {
                $this->syncLocalUserFromIdToken((string) $authResult['IdToken']);
                return response()->json([
                    'access_token' => $authResult['AccessToken'] ?? null,
                    'id_token' => $authResult['IdToken'] ?? null,
                    'refresh_token' => $authResult['RefreshToken'] ?? null,
                    'expires_in' => $authResult['ExpiresIn'] ?? null,
                ]);
            }

            return response()->json([
                'message' => 'Challenge required: ' . ($challengeData['ChallengeName'] ?? 'UNKNOWN'),
                'challenge' => $challengeData['ChallengeName'] ?? null,
                'session' => $challengeData['Session'] ?? null,
            ], 202);
        }

        if (empty($payload['password'])) {
            return response()->json([
                'message' => 'Password is required',
            ], 422);
        }

        $authParams = [
            'USERNAME' => $email,
            'PASSWORD' => $payload['password'],
        ];
        if ($secretHash) {
            $authParams['SECRET_HASH'] = $secretHash;
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
        if (($authResult['IdToken'] ?? null)) {
            $this->syncLocalUserFromIdToken((string) $authResult['IdToken']);
        }

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
        $localRole = null;
        if ($userId) {
            $localRole = \Illuminate\Support\Facades\DB::table('usuarios')
                ->join('roles', 'roles.id', '=', 'usuarios.rol_id')
                ->where('usuarios.id', (int) $userId)
                ->value('roles.codigo');
        }

        return response()->json([
            'id' => $userId,
            'sub' => $claims['sub'] ?? null,
            'email' => $claims['email'] ?? null,
            'username' => $claims['username'] ?? ($claims['cognito:username'] ?? null),
            'token_use' => $claims['token_use'] ?? null,
            'empresa_id' => $empresaId,
            'local_role' => $localRole,
            'claims' => $claims,
        ]);
    }

    private function syncLocalUserFromIdToken(string $idToken): void
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return;
        }

        $payloadB64 = strtr($parts[1], '-_', '+/');
        $padding = strlen($payloadB64) % 4;
        if ($padding > 0) {
            $payloadB64 .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode($payloadB64, true);
        if (! is_string($json)) {
            return;
        }

        $claims = json_decode($json, true);
        if (! is_array($claims)) {
            return;
        }

        $email = $claims['email'] ?? null;
        if (! is_string($email) || trim($email) === '') {
            $candidate = $claims['cognito:username'] ?? $claims['username'] ?? null;
            if (is_string($candidate) && str_contains($candidate, '@')) {
                $email = $candidate;
            }
        }
        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $email = mb_strtolower(trim($email));
        $exists = DB::table('usuarios')->whereRaw('LOWER(email)=?', [$email])->exists();
        if ($exists) {
            return;
        }

        $roleCode = null;
        $groups = $claims['cognito:groups'] ?? null;
        if (is_array($groups) && count($groups) > 0 && is_string($groups[0])) {
            $roleCode = mb_strtolower(trim($groups[0]));
        } elseif (is_string($groups) && trim($groups) !== '') {
            $roleCode = mb_strtolower(trim(explode(',', $groups)[0]));
        } elseif (is_string($claims['custom:role'] ?? null)) {
            $roleCode = mb_strtolower(trim((string) $claims['custom:role']));
        }

        $roleId = $roleCode
            ? DB::table('roles')->whereRaw('LOWER(codigo)=?', [$roleCode])->value('id')
            : null;
        if (! $roleId) {
            $roleId = DB::table('roles')->where('codigo', 'tecnico')->value('id');
        }
        if (! $roleId) {
            return;
        }

        $empresaId = $claims['custom:empresa_id'] ?? $claims['empresa_id'] ?? null;
        if ($empresaId) {
            $empresaExists = DB::table('empresas')->where('id', (int) $empresaId)->exists();
            if (! $empresaExists) {
                $empresaId = null;
            }
        }
        if (! $empresaId) {
            $empresaId = DB::table('empresas')->orderBy('id')->value('id');
        }
        if (! $empresaId) {
            return;
        }

        $nombre = trim((string) ($claims['given_name'] ?? 'Usuario'));
        $apellido = trim((string) ($claims['family_name'] ?? 'Cognito'));
        if ($nombre === '') $nombre = Str::title(Str::before($email, '@'));
        if ($apellido === '') $apellido = 'Cognito';

        DB::table('usuarios')->insert([
            'empresa_id' => (int) $empresaId,
            'rol_id' => (int) $roleId,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'password_hash' => password_hash(Str::random(32), PASSWORD_BCRYPT),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
