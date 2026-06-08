<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditTrail
{
    /**
     * Structured audit event for state-changing operations.
     *
     * Keep payload intentionally small and metadata-focused to avoid leaking
     * sensitive field values into logs.
     */
    public function record(string $event, array $context = []): void
    {
        Log::info('audit.trail', array_merge([
            'event' => $event,
            'ts' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Security-sensitive audit event (privilege escalation, reactivation, etc.).
     *
     * Emitted at WARNING level so it surfaces in SIEM/alerting pipelines that
     * filter by severity, separate from the routine INFO audit stream.
     */
    public function alert(string $event, array $context = []): void
    {
        Log::warning('audit.alert', array_merge([
            'event' => $event,
            'ts' => now()->toIso8601String(),
        ], $context));
    }
}

