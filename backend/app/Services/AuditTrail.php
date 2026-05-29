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
}

