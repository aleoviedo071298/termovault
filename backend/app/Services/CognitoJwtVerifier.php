<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CognitoJwtVerifier
{
    public function verify(string $jwt): array
    {
        [$header, $payload, $signature] = $this->splitJwt($jwt);

        $this->validateRegisteredClaims($payload);
        $this->validateHeader($header);
        $this->validateAudience($payload);
        $this->validateSignature($header, $payload, $signature, $jwt);

        return $payload;
    }

    private function splitJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid JWT format');
        }

        $header = $this->jsonDecode($this->base64UrlDecode($parts[0]));
        $payload = $this->jsonDecode($this->base64UrlDecode($parts[1]));
        $signature = $this->base64UrlDecode($parts[2]);

        return [$header, $payload, $signature];
    }

    private function validateRegisteredClaims(array $payload): void
    {
        $now = time();
        $leeway = (int) config('cognito.leeway', 60);

        if (($payload['exp'] ?? 0) < ($now - $leeway)) {
            throw new RuntimeException('Token expired');
        }

        if (($payload['iat'] ?? PHP_INT_MAX) > ($now + $leeway)) {
            throw new RuntimeException('Token issued in the future');
        }

        $expectedIssuer = $this->issuer();
        if (($payload['iss'] ?? '') !== $expectedIssuer) {
            throw new RuntimeException('Invalid issuer');
        }
    }

    private function validateHeader(array $header): void
    {
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('Unsupported JWT algorithm');
        }

        if (! isset($header['kid']) || ! is_string($header['kid'])) {
            throw new RuntimeException('Missing key id (kid)');
        }
    }

    private function validateAudience(array $payload): void
    {
        $clientId = (string) config('cognito.app_client_id');
        if ($clientId === '') {
            return;
        }

        $tokenUse = $payload['token_use'] ?? null;
        if ($tokenUse === 'id') {
            if (($payload['aud'] ?? null) !== $clientId) {
                throw new RuntimeException('Invalid audience');
            }

            return;
        }

        if (($payload['client_id'] ?? null) !== $clientId) {
            throw new RuntimeException('Invalid client_id');
        }
    }

    private function validateSignature(array $header, array $payload, string $signature, string $jwt): void
    {
        $kid = $header['kid'];
        $jwk = collect($this->jwks()['keys'] ?? [])
            ->first(fn (array $key): bool => ($key['kid'] ?? null) === $kid);

        if (! $jwk) {
            throw new RuntimeException('Signing key not found');
        }

        $publicKeyPem = $this->jwkToPem($jwk);
        $signingInput = substr($jwt, 0, strrpos($jwt, '.'));

        $verified = openssl_verify($signingInput, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new RuntimeException('Invalid JWT signature');
        }
    }

    private function jwks(): array
    {
        $cacheKey = 'cognito:jwks:'.$this->issuer();

        return Cache::remember($cacheKey, now()->addHours(6), function (): array {
            $response = Http::timeout(5)->get($this->issuer().'/.well-known/jwks.json');
            if (! $response->ok()) {
                throw new RuntimeException('Unable to fetch Cognito JWKS');
            }

            return $response->json();
        });
    }

    private function issuer(): string
    {
        $region = (string) config('cognito.region');
        $poolId = (string) config('cognito.user_pool_id');

        if ($region === '' || $poolId === '') {
            throw new RuntimeException('Cognito region/user pool not configured');
        }

        return "https://cognito-idp.{$region}.amazonaws.com/{$poolId}";
    }

    private function jwkToPem(array $jwk): string
    {
        $modulus = $this->base64UrlDecode($jwk['n'] ?? '');
        $exponent = $this->base64UrlDecode($jwk['e'] ?? '');

        if ($modulus === '' || $exponent === '') {
            throw new RuntimeException('Malformed JWKS key');
        }

        $modulus = $this->asn1Integer($modulus);
        $exponent = $this->asn1Integer($exponent);
        $rsaPublicKey = $this->asn1Sequence($modulus.$exponent);

        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
        $subjectPublicKey = $this->asn1BitString($rsaPublicKey);
        $subjectPublicKeyInfo = $this->asn1Sequence($algorithmIdentifier.$subjectPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function asn1Integer(string $value): string
    {
        if (ord($value[0]) > 0x7F) {
            $value = "\x00".$value;
        }

        return "\x02".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1BitString(string $value): string
    {
        return "\x03".$this->asn1Length(strlen($value) + 1)."\x00".$value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30".$this->asn1Length(strlen($value)).$value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($temp)).$temp;
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url content');
        }

        return $decoded;
    }

    private function jsonDecode(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JWT JSON segment');
        }

        return $decoded;
    }
}
