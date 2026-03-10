<?php

namespace Modules\Billing\Services;

use InvalidArgumentException;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\Contracts\BillingProviderInterface;
use Modules\Billing\Services\Providers\EFactBillingProvider;
use Modules\Billing\Services\Providers\GreenterBillingProvider;
use Modules\Billing\Services\Providers\NubefactBillingProvider;
use Modules\Billing\Services\Providers\TeFacturoBillingProvider;

class BillingProviderResolver
{
    /**
     * @return array<string, BillingProviderInterface>
     */
    private function providers(): array
    {
        return [
            'greenter' => app(GreenterBillingProvider::class),
            'nubefact' => app(NubefactBillingProvider::class),
            'tefacturo' => app(TeFacturoBillingProvider::class),
            'efact' => app(EFactBillingProvider::class),
        ];
    }

    public function resolveFromSetting(BillingSetting $setting): BillingProviderInterface
    {
        $provider = $this->providers()[$setting->provider] ?? null;
        if (! $provider) {
            throw new InvalidArgumentException('Proveedor de facturación no soportado: '.$setting->provider);
        }

        return $provider;
    }
}
