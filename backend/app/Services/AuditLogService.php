<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * Daftar nama field sensitif yang wajib dihapus sebelum disimpan ke audit_logs.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'plain_text_token',
        'plaintexttoken',
        'remember_token',
        'secret',
        'client_secret',
        'api_key',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>|null  $changesSummary
     */
    public function record(
        ?User $actor,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $changesSummary = null
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes_summary' => $changesSummary !== null ? $this->sanitize($changesSummary) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}

