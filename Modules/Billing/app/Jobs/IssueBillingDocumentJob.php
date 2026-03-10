<?php

namespace Modules\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\ElectronicBillingService;

class IssueBillingDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public int $documentId,
        public array $payload
    ) {
    }

    public function handle(ElectronicBillingService $billingService, SalesAccountingService $salesAccounting): void
    {
        $document = BillingDocument::query()->find($this->documentId);
        if (! $document) {
            return;
        }

        $result = $billingService->issue($document, $this->payload);
        if (! (bool) ($result['ok'] ?? false)) {
            return;
        }

        if ($document->order_id && $document->order) {
            $salesAccounting->postIssuedSale($document->order, $document->fresh());
        }
    }
}
