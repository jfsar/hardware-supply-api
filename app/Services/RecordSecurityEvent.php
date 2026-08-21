<?php

namespace App\Services;

use App\Enums\SecuritySeverity;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordSecurityEvent
{
    public function __construct(protected Request $request) {}

    /**
     * Persist a security event with request context.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        ?User $user,
        string $eventType,
        SecuritySeverity $severity = SecuritySeverity::Info,
        array $metadata = [],
    ): SecurityEvent {
        return SecurityEvent::create([
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'severity' => $severity->value,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->sanitizedUserAgent(),
            'request_id' => $this->requestId(),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * The user agent trimmed to the column length.
     */
    private function sanitizedUserAgent(): ?string
    {
        $userAgent = $this->request->userAgent();

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return substr($userAgent, 0, 500);
    }

    /**
     * The correlation id assigned by middleware, or a fresh one.
     */
    private function requestId(): string
    {
        $existing = $this->request->attributes->get('request_id');

        return is_string($existing) && $existing !== ''
            ? $existing
            : (string) Str::ulid();
    }
}
