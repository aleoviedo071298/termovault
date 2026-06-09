<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    /**
     * FIX [N-07]: rate limiters por usuario autenticado (no por IP).
     *
     * EnsureCognitoJwt no autentica al user en el guard nativo de Laravel —
     * deja `auth.user_id` en los attributes del request. Las claves usan ese
     * identificador, con fallback a IP para casos sin auth. Esto evita que
     * usuarios distintos compartan cubo cuando vienen desde la misma IP
     * (proxy corporativo, NAT, edge de Cloudflare).
     */
    private function registerRateLimiters(): void
    {
        $byUserOrIp = static function (Request $request): string {
            $userId = $request->attributes->get('auth.user_id');
            return $userId !== null ? 'user:' . $userId : 'ip:' . $request->ip();
        };

        // Cuota general para cualquier endpoint autenticado: 120 req/min.
        RateLimiter::for('api', function (Request $request) use ($byUserOrIp) {
            return Limit::perMinute(120)->by($byUserOrIp($request));
        });

        // Descargas: 30/min por usuario. Una descarga normal es ocasional;
        // 30/min detecta scraping de informes termográficos.
        RateLimiter::for('download', function (Request $request) use ($byUserOrIp) {
            return Limit::perMinute(30)->by($byUserOrIp($request));
        });

        // Endpoints admin de escritura: 30/min por usuario.
        RateLimiter::for('admin-write', function (Request $request) use ($byUserOrIp) {
            return Limit::perMinute(30)->by($byUserOrIp($request));
        });
    }
}
