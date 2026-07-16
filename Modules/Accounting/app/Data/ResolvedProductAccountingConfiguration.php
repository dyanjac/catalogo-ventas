<?php

declare(strict_types=1);

namespace Modules\Accounting\Data;

use Modules\Catalog\Enums\ProductAccountingTreatment;

final readonly class ResolvedProductAccountingConfiguration
{
    /**
     * @param  array<string, string|null>  $accounts
     * @param  array<string, string|null>  $accountSources
     */
    public function __construct(
        public ProductAccountingTreatment $treatment,
        public string $treatmentSource,
        public array $accounts,
        public array $accountSources,
    ) {}

    public function account(string $name): ?string
    {
        return $this->accounts[$name] ?? null;
    }

    public function accountSource(string $name): ?string
    {
        return $this->accountSources[$name] ?? null;
    }

    public function isAutomatic(): bool
    {
        return $this->treatment === ProductAccountingTreatment::Automatic;
    }
}
