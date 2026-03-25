<?php

namespace Modules\Security\Services;

use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Model;
use Modules\Security\Models\SecurityAuditLog;

class SecurityAuditService
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function log(
        string $eventType,
        string $eventCode,
        string $result = 'success',
        ?string $message = null,
        ?User $actor = null,
        mixed $target = null,
        ?string $module = 'security',
        array $context = []
    ): void {
        $targetUserId = $target instanceof User ? $target->id : null;

        if ($target instanceof Model && ! $target instanceof User) {
            $context = array_merge([
                'target_type' => $target::class,
                'target_id' => $target->getKey(),
            ], $context);
        }

        SecurityAuditLog::query()->create([
            'organization_id' => $actor?->organization_id
                ?? ($target instanceof User ? $target->organization_id : null)
                ?? $this->organizationContext->currentOrganizationId(),
            'actor_user_id' => $actor?->id,
            'target_user_id' => $targetUserId,
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
