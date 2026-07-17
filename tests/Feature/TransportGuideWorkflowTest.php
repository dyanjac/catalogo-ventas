<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\InventoryTransferItem;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Security\Models\SecurityBranch;
use Modules\Transport\Data\TransportGuideCommand;
use Modules\Transport\Data\TransportGuideItemData;
use Modules\Transport\Enums\TransportEnvironment;
use Modules\Transport\Enums\TransportGuideStatus;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;
use Modules\Transport\Services\Contracts\TransportGuideProviderInterface;
use Modules\Transport\Services\TransportGuideProviderResolver;
use Modules\Transport\Services\TransportGuideService;
use Tests\TestCase;

class TransportGuideWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_guide_simulation_is_idempotent_and_never_moves_inventory(): void
    {
        Storage::fake('local');
        $scope = $this->scope();
        $service = app(TransportGuideService::class);
        $command = $this->command($scope, 'gre-sender-replay');
        $before = InventoryMovement::query()->count();

        $guide = $service->create($command);
        $replay = $service->create($command);
        $this->assertSame($guide->id, $replay->id);
        $this->assertSame(TransportGuideStatus::Ready, $guide->status);
        $this->assertDatabaseCount('transport_guides', 1);
        $this->assertDatabaseCount('transport_guide_items', 1);
        $this->assertSame($before, InventoryMovement::query()->count());

        try {
            $service->create($this->command($scope, 'gre-sender-replay', quantity: 2));
            $this->fail('La misma clave con otro payload debio fallar.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transport_guides', 1);
        }

        $service->enqueue($scope['organization']->id, $guide->id);
        $submitted = $guide->fresh();
        $this->assertSame(TransportGuideStatus::Submitted, $submitted->status);
        $this->assertNotEmpty($submitted->provider_ticket);
        $this->assertSame($before, InventoryMovement::query()->count());
        Storage::disk('local')->assertExists((string) $submitted->xml_path);

        $accepted = $service->poll($scope['organization']->id, $guide->id);
        $replayedPoll = $service->poll($scope['organization']->id, $guide->id);
        $this->assertSame(TransportGuideStatus::Accepted, $accepted->status);
        $this->assertSame($accepted->id, $replayedPoll->id);
        $this->assertDatabaseCount('transport_guide_transmissions', 3);
        Storage::disk('local')->assertExists((string) $accepted->cdr_path);
        $this->assertSame($before, InventoryMovement::query()->count());
    }

    public function test_transfer_guide_requires_exact_internal_items_but_does_not_dispatch_transfer(): void
    {
        $scope = $this->scope();
        $destination = SecurityBranch::query()->create([
            'organization_id' => $scope['organization']->id, 'code' => 'DEST', 'name' => 'Destino', 'is_active' => true, 'is_default' => false,
        ]);
        $transfer = InventoryTransfer::query()->create([
            'organization_id' => $scope['organization']->id, 'code' => 'TRF-F7-001', 'idempotency_key' => 'trf-f7-001',
            'payload_hash' => hash('sha256', 'trf-f7-001'), 'source_branch_id' => $scope['branch']->id,
            'destination_branch_id' => $destination->id, 'status' => 'draft', 'created_by' => $scope['user']->id,
        ]);
        InventoryTransferItem::query()->create([
            'organization_id' => $scope['organization']->id, 'transfer_id' => $transfer->id,
            'product_id' => $scope['product']->id, 'quantity' => 3,
        ]);
        $before = InventoryMovement::query()->count();

        $guide = app(TransportGuideService::class)->create($this->command(
            $scope, 'gre-transfer-f7', reason: '04', quantity: 3, inventoryTransferId: $transfer->id,
        ));

        $this->assertSame($transfer->id, $guide->inventory_transfer_id);
        $this->assertSame('draft', $transfer->fresh()->status->value);
        $this->assertSame($before, InventoryMovement::query()->count());

        try {
            app(TransportGuideService::class)->create($this->command(
                $scope, 'gre-transfer-mismatch-f7', reason: '04', quantity: 2, inventoryTransferId: $transfer->id,
            ));
            $this->fail('La GRE debe coincidir con los items de la transferencia.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transport_guides', 1);
            $this->assertSame($before, InventoryMovement::query()->count());
        }
    }

    public function test_carrier_guide_requires_accepted_sender_or_documented_exception(): void
    {
        $scope = $this->scope();
        $service = app(TransportGuideService::class);
        $sender = $service->create($this->command($scope, 'gre-sender-for-carrier'));
        $service->enqueue($scope['organization']->id, $sender->id);

        try {
            $service->create($this->command($scope, 'gre-carrier-before-acceptance', type: TransportGuideType::Carrier, mode: TransportMode::Public, relatedGuideId: $sender->id));
            $this->fail('La GRE transportista no debe usar una remitente pendiente.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transport_guides', 1);
        }

        $service->poll($scope['organization']->id, $sender->id);
        $carrier = $service->create($this->command($scope, 'gre-carrier-accepted', type: TransportGuideType::Carrier, mode: TransportMode::Public, relatedGuideId: $sender->id));
        $this->assertSame(TransportGuideType::Carrier, $carrier->guide_type);
        $this->assertSame($sender->id, $carrier->related_guide_id);
        $this->assertStringStartsWith('V', $carrier->series);

        try {
            $service->create($this->command($scope, 'gre-carrier-no-sender', type: TransportGuideType::Carrier, mode: TransportMode::Public));
            $this->fail('La excepcion no configurada debio fallar.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transport_guides', 2);
        }

        $scope['setting']->forceFill(['allow_carrier_without_sender' => true])->save();
        $exception = $service->create($this->command(
            $scope, 'gre-carrier-exception', type: TransportGuideType::Carrier, mode: TransportMode::Public,
            exception: 'Remitente no obligado; evidencia archivada en expediente F7-001.',
        ));
        $this->assertNull($exception->related_guide_id);
        $this->assertNotEmpty($exception->exception_justification);
    }

    public function test_production_submission_is_blocked_until_current_credentials_are_validated(): void
    {
        $scope = $this->scope();
        $scope['setting']->forceFill([
            'environment' => TransportEnvironment::Production->value,
            'provider' => 'greenter',
            'provider_credentials' => [],
            'credentials_hash' => null,
            'credentials_validated_at' => null,
        ])->save();
        $guide = app(TransportGuideService::class)->create($this->command($scope, 'gre-production-blocked'));

        $this->expectException(ValidationException::class);
        app(TransportGuideService::class)->enqueue($scope['organization']->id, $guide->id);
    }

    public function test_submit_retry_and_transient_poll_never_duplicate_a_ticketed_guide(): void
    {
        $scope = $this->scope();
        $guide = app(TransportGuideService::class)->create($this->command($scope, 'gre-safe-retry'));
        $provider = \Mockery::mock(TransportGuideProviderInterface::class);
        $provider->shouldReceive('submit')->twice()->andReturn(
            ['status' => 'error', 'provider_code' => 'TEMP', 'message' => 'Temporal'],
            ['status' => 'submitted', 'provider_code' => '98', 'ticket' => 'SAFE-TICKET'],
        );
        $provider->shouldReceive('poll')->once()->andReturn([
            'status' => 'error', 'provider_code' => 'QUERY_EXCEPTION', 'message' => 'Consulta temporal',
        ]);
        $resolver = \Mockery::mock(TransportGuideProviderResolver::class);
        $resolver->shouldReceive('resolve')->with('simulation')->andReturn($provider);
        $this->app->instance(TransportGuideProviderResolver::class, $resolver);
        $service = app(TransportGuideService::class);

        $this->assertSame(TransportGuideStatus::Error, $service->enqueue($scope['organization']->id, $guide->id)->status);
        $submitted = $service->enqueue($scope['organization']->id, $guide->id);
        $this->assertSame(TransportGuideStatus::Submitted, $submitted->status);
        $this->assertSame('SAFE-TICKET', $submitted->provider_ticket);
        $this->assertSame(4, $submitted->transmissions()->count());

        $polled = $service->poll($scope['organization']->id, $guide->id);
        $this->assertSame(TransportGuideStatus::Submitted, $polled->status);
        $this->assertSame('SAFE-TICKET', $polled->provider_ticket);
        $this->assertSame(5, $polled->transmissions()->count());
        $this->assertSame(TransportGuideStatus::Submitted, $service->enqueue($scope['organization']->id, $guide->id)->status);
        $this->assertSame(5, $guide->transmissions()->count());
    }

    public function test_carrier_accepts_immutable_external_sender_reference(): void
    {
        $scope = $this->scope();
        $guide = app(TransportGuideService::class)->create($this->command(
            $scope,
            'gre-carrier-external',
            type: TransportGuideType::Carrier,
            mode: TransportMode::Public,
            externalSender: ['document_type' => '09', 'number' => 'T123-456', 'issuer_ruc' => '20123456789'],
        ));

        $this->assertSame('T123-456', $guide->external_sender_snapshot['number']);
        $this->assertSame('20123456789', $guide->external_sender_snapshot['issuer_ruc']);
        $this->expectException(\LogicException::class);
        $guide->external_sender_snapshot = ['document_type' => '09', 'number' => 'T999-1', 'issuer_ruc' => '20123456789'];
        $guide->save();
    }

    public function test_uncertain_submission_entitlement_and_configuration_changes_are_fail_closed(): void
    {
        $scope = $this->scope();
        $service = app(TransportGuideService::class);
        $guide = $service->create($this->command($scope, 'gre-fail-closed'));
        $guide->forceFill(['status' => TransportGuideStatus::Submitting->value])->save();
        $uncertain = $service->markSubmissionUncertain($scope['organization']->id, $guide->id, 'worker terminated');
        $this->assertSame(TransportGuideStatus::Uncertain, $uncertain->status);
        $this->assertSame('submission_uncertain', $uncertain->transmissions()->latest('id')->value('operation'));

        try {
            $service->enqueue($scope['organization']->id, $guide->id);
            $this->fail('Una transmision incierta no debe reenviarse automaticamente.');
        } catch (ValidationException) {
            $this->assertSame(TransportGuideStatus::Uncertain, $guide->fresh()->status);
        }

        $configured = $service->create($this->command($scope, 'gre-config-fingerprint'));
        $scope['setting']->forceFill(['sender_series' => 'T999'])->save();
        try {
            $service->enqueue($scope['organization']->id, $configured->id);
            $this->fail('No debe emitirse con una configuracion distinta a la preparada.');
        } catch (ValidationException) {
            $this->assertSame(TransportGuideStatus::Ready, $configured->fresh()->status);
        }

        $scope['setting']->forceFill(['sender_series' => 'T001'])->save();
        DB::table('organization_entitlements')->where('organization_id', $scope['organization']->id)->delete();
        $this->expectException(ValidationException::class);
        $service->enqueue($scope['organization']->id, $configured->id);
    }

    public function test_tenant_links_and_schema_rollback_with_evidence_are_blocked(): void
    {
        $scope = $this->scope();
        $other = $this->scope('OTHER');
        $sender = app(TransportGuideService::class)->create($this->command($other, 'gre-other-tenant'));

        try {
            app(TransportGuideService::class)->create($this->command(
                $scope, 'gre-cross-tenant', type: TransportGuideType::Carrier, mode: TransportMode::Public, relatedGuideId: $sender->id,
            ));
            $this->fail('No debe vincularse una GRE de otro tenant.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transport_guides', 1);
        }

        try {
            $migration = require base_path('Modules/Transport/database/migrations/2026_07_17_100000_create_transport_guide_domain.php');
            $migration->down();
            $this->fail('El rollback con evidencia GRE debio bloquearse.');
        } catch (\RuntimeException) {
            $this->assertTrue(TransportGuide::query()->whereKey($sender->id)->exists());
        }
    }

    /** @return array<string, mixed> */
    private function scope(string $suffix = 'MAIN'): array
    {
        $organization = Organization::query()->create([
            'code' => 'F7-'.$suffix.'-'.uniqid(), 'name' => 'Phase 07 '.$suffix, 'slug' => 'phase07-'.strtolower($suffix).'-'.uniqid(),
            'status' => 'active', 'environment' => 'demo', 'is_default' => $suffix === 'MAIN',
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'F7-'.$suffix, 'name' => 'Sucursal '.$suffix,
            'city' => 'Lima', 'address' => 'Av. Partida 100', 'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id]);
        $this->actingAs($user);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Bienes F7 '.$suffix, 'slug' => 'bienes-f7-'.strtolower($suffix).'-'.uniqid(),
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Producto F7 '.$suffix,
            'sku' => 'F7-'.$suffix.'-'.uniqid(), 'slug' => 'producto-f7-'.strtolower($suffix).'-'.uniqid(),
            'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 10, 'sale_price' => 10, 'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        $setting = TransportSetting::query()->create([
            'organization_id' => $organization->id, 'enabled' => true, 'environment' => 'simulation',
            'provider' => 'simulation', 'dispatch_mode' => 'sync', 'queue_connection' => 'sync', 'queue_name' => 'transport',
            'sender_series' => 'T001', 'carrier_series' => 'V001', 'allow_carrier_without_sender' => false,
        ]);
        DB::table('organization_entitlements')->insert([
            'organization_id' => $organization->id,
            'capability_id' => DB::table('saas_capabilities')->where('code', 'transport.gre')->value('id'),
            'state' => 'enabled',
            'source' => 'phase07-test',
            'reason' => 'Cobertura funcional FASE 07',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('organization', 'branch', 'user', 'product', 'setting');
    }

    /** @param array<string, mixed> $scope */
    private function command(
        array $scope,
        string $key,
        TransportGuideType $type = TransportGuideType::Sender,
        TransportMode $mode = TransportMode::Private,
        string $reason = '01',
        float $quantity = 1,
        ?int $inventoryTransferId = null,
        ?int $relatedGuideId = null,
        ?array $externalSender = null,
        ?string $exception = null,
    ): TransportGuideCommand {
        $transport = $mode === TransportMode::Private
            ? ['vehicle_plate' => 'ABC-123', 'driver_document_number' => '12345678', 'driver_name' => 'Juan', 'driver_last_name' => 'Perez', 'driver_license' => 'Q12345678']
            : ['carrier_document_type' => '6', 'carrier_document_number' => '20123456789', 'carrier_name' => 'Transportes F7 SAC', 'mtc_registration' => 'MTC-001'];

        return new TransportGuideCommand(
            organizationId: $scope['organization']->id,
            branchId: $scope['branch']->id,
            idempotencyKey: $key,
            type: $type,
            reasonCode: $reason,
            transportMode: $mode,
            transferDate: new \DateTimeImmutable('+1 hour'),
            origin: ['ubigeo' => '150101', 'address' => 'Av. Partida 100', 'establishment_code' => '0001'],
            destination: ['ubigeo' => '150102', 'address' => 'Av. Llegada 200', 'establishment_code' => '0002'],
            recipient: ['document_type' => '6', 'document_number' => '20987654321', 'name' => 'Cliente F7 SAC'],
            transport: $transport,
            items: [new TransportGuideItemData($scope['product']->id, (string) $scope['product']->sku, (string) $scope['product']->name, $quantity)],
            grossWeight: 12.5,
            packageCount: 1,
            inventoryTransferId: $inventoryTransferId,
            relatedGuideId: $relatedGuideId,
            externalSender: $externalSender,
            exceptionJustification: $exception,
            actorId: $scope['user']->id,
        );
    }
}
