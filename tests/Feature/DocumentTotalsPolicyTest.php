<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DocumentTotals;
use App\Services\RoundingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\Providers\GreenterBillingProvider;
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

    public function test_greenter_xml_preserves_line_discount_shipping_and_decimal_precision(): void
    {
        $setting = new BillingSetting([
            'environment' => 'sandbox',
            'default_invoice_operation_code' => '',
            'provider_credentials' => ['greenter' => ['ruc' => '20123456789']],
        ]);
        $payload = [
            'document_type' => 'factura', 'series' => 'F001', 'number' => '1',
            'issue_date' => '2026-10-10', 'currency' => 'PEN', 'tax_rate' => '0.18',
            'customer' => ['name' => 'Cliente', 'document_type' => 'RUC', 'document_number' => '20987654321'],
            'totals' => ['subtotal' => '10.00', 'discount' => '1.00', 'tax' => '1.62', 'shipping' => '2.00', 'total' => '12.62'],
            'items' => [[
                'sku' => 'MICRO', 'name' => 'Micro costo', 'quantity' => '0.0005',
                'unit_price' => '20000.123456', 'line_subtotal' => '10.00',
                'line_discount' => '1.00', 'line_tax' => '1.62', 'line_total' => '10.62',
            ]],
        ];

        $invoice = (new \ReflectionMethod(GreenterBillingProvider::class, 'buildInvoiceFromPayload'))
            ->invoke(app(GreenterBillingProvider::class), $setting, $payload, '2.1');
        $xml = (new \Greenter\Xml\Builder\InvoiceBuilder)->build($invoice);

        $this->assertStringContainsString('<cbc:IssueDate>2026-10-10</cbc:IssueDate>', $xml);
        $this->assertStringContainsString('<cbc:InvoicedQuantity unitCode="NIU">0.0005</cbc:InvoicedQuantity>', $xml);
        $this->assertStringContainsString('<cbc:PriceAmount currencyID="PEN">20000.123456</cbc:PriceAmount>', $xml);
        $this->assertStringContainsString('<cbc:AllowanceChargeReasonCode>00</cbc:AllowanceChargeReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:AllowanceChargeReasonCode>50</cbc:AllowanceChargeReasonCode>', $xml);
        $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="PEN">10.62</cbc:TaxInclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:ChargeTotalAmount currencyID="PEN">2.00</cbc:ChargeTotalAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="PEN">12.62</cbc:PayableAmount>', $xml);
    }
}
