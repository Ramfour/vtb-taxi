<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function log(?User $user, string $action, ?Model $entity = null, array $oldValues = [], array $newValues = []): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_type' => $entity ? class_basename($entity) : 'system',
                'entity_id' => $entity?->getKey(),
                'old_values' => $oldValues !== [] ? $oldValues : null,
                'new_values' => $newValues !== [] ? $newValues : null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Audit logging must not break the main flow.
        }
    }

    public static function logFor(?User $user, string $action, string $entityType, ?int $entityId = null, array $oldValues = [], array $newValues = []): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues !== [] ? $oldValues : null,
                'new_values' => $newValues !== [] ? $newValues : null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            // Audit logging must not break the main flow.
        }
    }
}
