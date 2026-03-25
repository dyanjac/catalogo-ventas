<?php

namespace Modules\Accounting\Services;

use App\Services\OrganizationContextService;
use Modules\Accounting\Models\AccountingAuditLog;

class AccountingAuditService
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function log(string $entityType, ?int $entityId, string $action, array $payload = []): void
    {
        AccountingAuditLog::query()->create([
            'organization_id' => auth()->user()?->organization_id ?? $this->organizationContext->currentOrganizationId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'payload' => $payload,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }
}
