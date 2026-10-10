<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DocumentTotals;
use App\Services\RoundingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\Xml\BillingXmlGenerator;
use Tests\TestCase;

class DocumentTotalsPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_line_rounding_precedes_tax_and_branch_can_override_global_mode(): void
    {
        $organizationId = DB::table('organizations')->insertGetId([
            'code' => 'ROUNDING', 'name' => 'Rounding', 'slug' => 'rounding',
            'status' => 'active', 'environment' => 'demo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('commerce_settings')->insert([
            'organization_id' => $organizationId, 'company_name' => 'Rounding', 'email' => 'rounding@example.test',
            'rounding_mode' => 'half_up', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('security_branches')->insertGetId([
            'organization_id' => $organizationId, 'code' => 'ROUND-B', 'name' => 'Branch',
            'rounding_mode' => 'half_even', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $lines = [
            ['quantity' => '1.0000', 'unit_price' => '0.025000'],
            ['quantity' => '1.0000', 'unit_price' => '0.025000'],
        ];
        $totals = app(DocumentTotals::class);
        $global = $totals->calculate($lines, '0', '0', '0.5', $organizationId);
        $branch = $totals->calculate($lines, '0', '0', '0.5', $organizationId, $branchId);

        $this->assertSame('0.06', $global['subtotal']);
        $this->assertSame('0.04', $global['tax']);
        $this->assertSame('0.10', $global['total']);
        $this->assertSame('0.04', $branch['subtotal']);
        $this->assertSame('0.02', $branch['tax']);
        $this->assertSame('0.06', $branch['total']);
        $this->assertSame('half_even', app(RoundingPolicy::class)->mode($organizationId, $branchId));

        $smallLines = array_fill(0, 10, ['quantity' => '1', 'unit_price' => '0.010000']);
        $discounted = $totals->calculate($smallLines, '0.05', '0', '0', $organizationId, $branchId);
        $this->assertSame('0.05', $discounted['discount']);
        $this->assertSame('0.05', $discounted['total']);
        $this->assertSame('0.05', array_reduce($discounted['lines'], fn (string $sum, array $line): string => \App\Support\Decimal::add($sum, $line['discount'], 2), '0'));
    }

    public function test_billing_xml_keeps_quantity_and_unit_price_precision(): void
    {
        Storage::fake('public');
        $document = new BillingDocument([
            'document_type' => 'factura', 'series' => 'F001', 'number' => '1',
            'subtotal' => '0.00', 'tax' => '0.00', 'total' => '0.00', 'currency' => 'PEN',
        ]);

        $path = app(BillingXmlGenerator::class)->generate($document, [
            'items' => [[
                'sku' => 'MICRO', 'name' => 'Micro costo', 'quantity' => '0.0005',
                'unit_price' => '0.123456', 'line_subtotal' => '0.00',
            ]],
        ]);
        $xml = Storage::disk('public')->get($path);

        $this->assertStringContainsString('<Quantity>0.0005</Quantity>', $xml);
        $this->assertStringContainsString('<UnitPrice>0.123456</UnitPrice>', $xml);
        $this->assertStringContainsString('<LineSubtotal>0.00</LineSubtotal>', $xml);
    }
}
