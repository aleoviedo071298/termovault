<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * FIX [R-01] — Hardening de TrustProxies.
 *
 * El middleware confiaba en cualquier proxy ('*'), permitiendo en teoría que
 * un cliente que llegara directo al origen spoofeara X-Forwarded-For para
 * evadir el rate-limit por IP. Hoy no es explotable porque [N-01] cierra el
 * origen a Cloudflare, pero como defense-in-depth ahora la lista se limita a
 * los rangos de Cloudflare.
 *
 * Estos tests verifican el contrato:
 *   1. Una IP de Cloudflare en REMOTE_ADDR → Laravel confía en XFF.
 *   2. Una IP NO Cloudflare en REMOTE_ADDR → Laravel ignora XFF y usa la IP
 *      directa.
 */
class TrustedProxiesTest extends TestCase
{
    /**
     * Verifica el comportamiento aplicando los trusted proxies del config
     * directamente sobre una Request de Symfony — sin depender del routing.
     * Es exactamente lo que hace Illuminate\Http\Middleware\TrustProxies
     * internamente, así que cubre el flujo real con menos fricción de tests.
     */
    private function probeIp(string $remoteAddr, ?string $xff = null): string
    {
        $headers = $xff !== null ? ['HTTP_X_FORWARDED_FOR' => $xff] : [];
        $request = \Illuminate\Http\Request::create(
            uri: '/_probe', method: 'GET', server: array_merge(['REMOTE_ADDR' => $remoteAddr], $headers)
        );

        $proxies = config('trustedproxies.proxies');
        $request->setTrustedProxies(
            $proxies,
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
        );

        return (string) $request->ip();
    }

    public function test_cloudflare_ipv4_is_trusted_and_xff_is_honored(): void
    {
        // Una IP de uno de los rangos de Cloudflare (104.16.0.0/13 incluye 104.16.0.1)
        $ip = $this->probeIp('104.16.0.1', '203.0.113.99');
        $this->assertSame('203.0.113.99', $ip, 'XFF debe ser confiado si REMOTE_ADDR es CF');
    }

    public function test_cloudflare_ipv6_is_trusted_and_xff_is_honored(): void
    {
        // 2606:4700::/32 — un rango IPv6 de Cloudflare
        $ip = $this->probeIp('2606:4700::1', '203.0.113.50');
        $this->assertSame('203.0.113.50', $ip, 'XFF debe ser confiado si REMOTE_ADDR es CF IPv6');
    }

    public function test_non_cloudflare_ip_is_NOT_trusted_xff_is_ignored(): void
    {
        // IP arbitraria fuera de los rangos de Cloudflare
        $attacker = '198.51.100.5'; // TEST-NET-2, definitivamente no es CF
        $ip = $this->probeIp($attacker, '203.0.113.99');
        $this->assertSame($attacker, $ip, 'XFF NO debe ser confiado si REMOTE_ADDR no es CF (anti-spoof)');
    }

    public function test_default_config_contains_cloudflare_ranges(): void
    {
        $proxies = config('trustedproxies.proxies');
        $this->assertIsArray($proxies);
        $this->assertContains('104.16.0.0/13', $proxies, 'Rango IPv4 de CF debe estar en la lista');
        $this->assertContains('2606:4700::/32', $proxies, 'Rango IPv6 de CF debe estar en la lista');
        // Total esperado: 15 IPv4 + 7 IPv6 = 22
        $this->assertCount(22, $proxies);
    }

    public function test_default_does_NOT_trust_wildcard_or_localhost(): void
    {
        $proxies = config('trustedproxies.proxies');
        $this->assertNotContains('*', $proxies, 'No debe confiar en wildcard');
        $this->assertNotContains('127.0.0.1', $proxies, 'No debe confiar en localhost por defecto');
        $this->assertNotContains('0.0.0.0/0', $proxies, 'No debe confiar en todo Internet');
    }
}
