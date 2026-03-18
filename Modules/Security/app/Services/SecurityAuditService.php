<?php

namespace Modules\Security\Services;

use App\Models\User;
use Modules\Security\Models\SecurityAuditLog;

class SecurityAuditService
{
    public function log(
        string $eventType,
        string $eventCode,
        string $result = 'success',
        ?string $message = null,
        ?User $actor = null,
        ?User $target = null,
        ?string $module = 'security',
        array $context = []
    ): void {
        SecurityAuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'event_type' => $eventType,
            'event_code' => $eventCode,
            'module' => $module,
            'result' => $result,
            'message' => $message,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
