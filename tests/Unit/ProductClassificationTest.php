<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductClassificationTest extends TestCase
{
    /**
     * @return array<string, array{ProductType, bool}>
     */
    public static function inventoryTypes(): array
    {
        return [
            'physical good' => [ProductType::PhysicalGood, true],
            'kit' => [ProductType::Kit, true],
            'service' => [ProductType::Service, false],
            'subscription' => [ProductType::Subscription, false],
            'digital' => [ProductType::Digital, false],
            'informational' => [ProductType::Informational, false],
        ];
    }

    #[DataProvider('inventoryTypes')]
    public function test_product_type_declares_if_it_tracks_inventory(ProductType $type, bool $expected): void
    {
        $this->assertSame($expected, $type->tracksInventory());
    }

    public function test_legacy_accounting_flag_maps_to_compatible_treatment(): void
    {
        $this->assertSame(
            ProductAccountingTreatment::Automatic,
            ProductAccountingTreatment::fromLegacyFlag(true)
        );
        $this->assertSame(
            ProductAccountingTreatment::NotApplicable,
            ProductAccountingTreatment::fromLegacyFlag(false)
        );
    }

    public function test_product_requests_do_not_accept_operational_stock_or_average_cost(): void
    {
        $storeRules = (new StoreProductRequest)->rules();
        $updateRules = (new UpdateProductRequest)->rules();

        $this->assertArrayNotHasKey('stock', $storeRules);
        $this->assertArrayNotHasKey('average_price', $storeRules);
        $this->assertArrayNotHasKey('stock', $updateRules);
        $this->assertArrayNotHasKey('average_price', $updateRules);
    }
}
