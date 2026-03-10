<?php

namespace Modules\Billing\Services\Contracts;

use Modules\Billing\Models\BillingSetting;

interface BillingProviderInterface
{
    public function code(): string;

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(BillingSetting $setting): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issueDocument(BillingSetting $setting, array $payload): array;
}
