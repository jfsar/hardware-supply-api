<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RecordAuditLog
{
    /**
     * Field names stripped from old/new values before persisting (NFR-SEC-010).
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'secret', 'two_factor_secret',
        'two_factor_recovery_codes', 'remember_token', 'api_token',
    ];

    public function __construct(protected Request $request) {}

    /**
     * Persist an audit trail row for an admin mutation.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __invoke(
        ?User $actor,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => (string) $this->request->ip(),
            'user_agent' => $this->sanitizedUserAgent(),
            'request_id' => $this->requestId(),
        ]);
    }

    /**
     * Record a mutation of an Eloquent resource with before/after snapshots.
     */
    public function model(?User $actor, string $action, Model $resource, ?array $oldValues = null): AuditLog
    {
        return $this(
            $actor,
            $action,
            class_basename($resource),
            $resource->getKey() === null ? null : (int) $resource->getKey(),
            $oldValues,
            $resource->getAttributes(),
        );
    }

    /**
     * Remove sensitive fields and cap payload size before persistence.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = Arr::except($values, self::SENSITIVE_KEYS);

        return $clean === [] ? null : Arr::only($clean, array_slice(array_keys($clean), 0, 50));
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
