<?php

namespace App\Http\Controllers;

use App\Services\Auth\LocalUserProvisioner;
use App\Services\CognitoJwtVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly LocalUserProvisioner $provisioner,
        private readonly CognitoJwtVerifier $verifier,
    ) {}

    /**
     * FIX [L-02]: verifica firma + claims del IdToken recibido de Cognito antes
     * de usarlo para aprovisionar el usuario local. Antes solo se hacía
     * base64+json_decode sin validar la firma — defense-in-depth contra
     * MITM en la conexión server→Cognito (Guzzle ya valida TLS, esto suma
     * verificación criptográfica de la cadena RSA/JWKS de Cognito).
     *
     * Si el token no verifica, NO se aprovisiona el usuario local pero el
     * login no falla (el cliente sí recibió tokens válidos de Cognito;
     * solo perdemos el sync local). Se loguea para diagnóstico.
     */
    private function verifyAndProvision(?string $idToken): void
    {
        if ($idToken === null || $idToken === '') {
            return;
        }
        try {
            $claims = $this->verifier->verify($idToken);
            $this->provisioner->findOrProvisionFromClaims($claims);
        } catch (Throwable $e) {
            Log::warning('auth.idtoken.verify_failed', [
                'event' => 'auth.idtoken.verify_failed',
                'reason' => $e->getMessage(),
            ]);
        }
    }

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
                'message' => 'Servicio de autenticacion no disponible temporalmente.',
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
                $this->logLoginFailure($request, $email, $challengeData, 'new_password_challenge');
                return response()->json([
                    'message' => $this->translateCognitoError($challengeData['__type'] ?? null, $challengeData['message'] ?? null),
                ], 401);
            }

            $authResult = $challengeData['AuthenticationResult'] ?? [];
            if (($authResult['AccessToken'] ?? null) && ($authResult['IdToken'] ?? null)) {
                $this->logLoginSuccess($request, $email, 'new_password_challenge');
                // FIX [L-02]: verificación de firma + claims, no solo decode.
                $this->verifyAndProvision((string) $authResult['IdToken']);
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
            $this->logLoginFailure($request, $email, $data, 'password_auth');
            $message = $this->translateCognitoError($data['__type'] ?? null, $data['message'] ?? null);
            return response()->json([
                'message' => $message,
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
            $this->logLoginSuccess($request, $email, 'password_auth');
            // FIX [L-02]: verificación de firma + claims, no solo decode.
            $this->verifyAndProvision((string) $authResult['IdToken']);
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

        // Extract Cognito groups from claims, if present.
        $groups = $claims['cognito:groups'] ?? [];
        if (!is_array($groups)) {
            $groups = [];
        }

        $effectiveGroups = $localRole ? [$localRole] : $groups;

        // Return only necessary information; no full JWT claims.
        return response()->json([
            'id' => $userId,
            'email' => $claims['email'] ?? null,
            'sub' => $claims['sub'] ?? null,
            'groups' => $effectiveGroups,
            'local_role' => $localRole,
            'empresa_id' => $empresaId,
        ]);
    }

    /**
     * FIX [001]: registra un intento de login fallido para monitoreo de seguridad
     * (detección de fuerza bruta / credential stuffing). Nivel WARNING para SIEM.
     */
    private function logLoginFailure(Request $request, string $email, ?array $cognito, string $flow): void
    {
        Log::warning('auth.login.failed', [
            'event' => 'auth.login.failed',
            'email' => $email,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'flow' => $flow,
            'reason' => $cognito['__type'] ?? 'unknown',
        ]);
    }

    /**
     * FIX [001]: registra un login exitoso (nivel INFO) para trazabilidad de accesos.
     * No se registran tokens ni contraseñas.
     */
    private function logLoginSuccess(Request $request, string $email, string $flow): void
    {
        Log::info('auth.login.success', [
            'event' => 'auth.login.success',
            'email' => $email,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'flow' => $flow,
        ]);
    }

    private function translateCognitoError(?string $type, ?string $message): string
    {
        $raw = mb_strtolower((string) ($type . ' ' . $message));

        if (str_contains($raw, 'notauthorized') || str_contains($raw, 'incorrect username or password')) {
            return 'Email o contraseña incorrectos.';
        }
        if (str_contains($raw, 'usernotfound')) {
            return 'No existe un usuario registrado con ese email.';
        }
        if (str_contains($raw, 'usernotconfirmed')) {
            return 'El usuario todavía no está confirmado.';
        }
        if (str_contains($raw, 'passwordresetrequired')) {
            return 'Debes restablecer tu contraseña antes de ingresar.';
        }
        if (str_contains($raw, 'invalidpassword')) {
            return 'La nueva contraseña no cumple los requisitos de seguridad.';
        }
        if (str_contains($raw, 'limitexceeded') || str_contains($raw, 'toomanyrequests')) {
            return 'Demasiados intentos. Espera unos minutos y vuelve a probar.';
        }
        if (str_contains($raw, 'expired')) {
            return 'La sesión expiró. Inicia sesión nuevamente.';
        }

        return 'No se pudo iniciar sesión. Verifica tus datos e intenta nuevamente.';
    }
}
