<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\AccountingAuditLog;

class AccountingAuditService
{
    public function log(string $entityType, ?int $entityId, string $action, array $payload = []): void
    {
        AccountingAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'payload' => $payload,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }
}
