<?php

declare(strict_types=1);

namespace Modules\Transport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\Product;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityScopeService;
use Modules\Transport\Data\TransportGuideCommand;
use Modules\Transport\Data\TransportGuideItemData;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Services\TransportGuideService;

class TransportGuideController extends Controller
{
    public function index(Request $request, SecurityScopeService $scope): View
    {
        $guides = $scope->scopeTransportGuides(TransportGuide::query(), $request->user())
            ->with(['branch', 'creator'])->latest('id')->paginate(20);

        return view('transport::guides.index', compact('guides'));
    }

    public function create(Request $request, OrganizationContextService $context, SecurityScopeService $scope): View
    {
        $organizationId = $this->organizationId($context);
        $branches = $scope->scopeBranches(SecurityBranch::query(), $request->user(), 'transport')->where('is_active', true)->orderBy('name')->get();

        return view('transport::guides.create', [
            'idempotencyKey' => (string) Str::uuid(),
            'branches' => $branches,
            'products' => Product::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get(),
            'inventoryDocuments' => $scope->scopeInventoryDocuments(InventoryDocument::query(), $request->user(), 'transport')->whereIn('document_type', ['dispatch', 'customer_return', 'supplier_return'])->latest('id')->limit(100)->get(),
            'inventoryTransfers' => $scope->scopeInventoryTransfers(InventoryTransfer::query(), $request->user(), 'transport')->latest('id')->limit(100)->get(),
            'billingDocuments' => $scope->scopeBillingDocuments(BillingDocument::query(), $request->user(), 'transport')->whereIn('document_type', ['factura', 'boleta'])->where('status', 'issued')->latest('id')->limit(100)->get(),
            'senderGuides' => $scope->scopeTransportGuides(TransportGuide::query(), $request->user())->where('guide_type', 'sender')->whereIn('status', ['accepted', 'accepted_with_observation'])->latest('id')->limit(100)->get(),
            'reasons' => config('transport.reasons', []),
        ]);
    }

    public function store(Request $request, OrganizationContextService $context, SecurityScopeService $scope, TransportGuideService $service): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:160'],
            'branch_id' => ['required', 'integer'],
            'guide_type' => ['required', Rule::enum(TransportGuideType::class)],
            'reason_code' => ['required', Rule::in(array_keys(config('transport.reasons', [])))],
            'transport_mode' => ['required', Rule::enum(TransportMode::class)],
            'transfer_at' => ['required', 'date'],
            'origin.ubigeo' => ['required', 'digits:6'],
            'origin.address' => ['required', 'string', 'max:500'],
            'origin.establishment_code' => ['nullable', 'string', 'max:10'],
            'destination.ubigeo' => ['required', 'digits:6'],
            'destination.address' => ['required', 'string', 'max:500'],
            'destination.establishment_code' => ['nullable', 'string', 'max:10'],
            'recipient.document_type' => ['required', 'string', 'max:3'],
            'recipient.document_number' => ['required', 'string', 'max:20'],
            'recipient.name' => ['required', 'string', 'max:250'],
            'transport' => ['required', 'array'],
            'gross_weight' => ['required', 'numeric', 'gt:0'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'inventory_document_id' => ['nullable', 'integer'],
            'inventory_transfer_id' => ['nullable', 'integer'],
            'billing_document_id' => ['nullable', 'integer'],
            'related_guide_id' => ['nullable', 'integer'],
            'external_sender' => ['nullable', 'array:document_type,number,issuer_ruc'],
            'external_sender.document_type' => ['nullable', Rule::in(['09'])],
            'external_sender.number' => ['nullable', 'regex:/^[T][A-Z0-9]{3}-\d{1,8}$/'],
            'external_sender.issuer_ruc' => ['nullable', 'digits:11'],
            'exception_justification' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.code' => ['required', 'string', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_code' => ['required', 'string', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless(
            $scope->scopeBranches(SecurityBranch::query(), $request->user(), 'transport')
                ->whereKey((int) $data['branch_id'])->where('is_active', true)->exists(),
            403,
        );
        $items = array_map(fn (array $item): TransportGuideItemData => new TransportGuideItemData(
            isset($item['product_id']) ? (int) $item['product_id'] : null,
            (string) $item['code'], (string) $item['description'], (float) $item['quantity'], (string) $item['unit_code'],
        ), $data['items']);
        $guide = $service->create(new TransportGuideCommand(
            organizationId: $this->organizationId($context),
            branchId: (int) $data['branch_id'],
            idempotencyKey: (string) $data['idempotency_key'],
            type: TransportGuideType::from((string) $data['guide_type']),
            reasonCode: (string) $data['reason_code'],
            transportMode: TransportMode::from((string) $data['transport_mode']),
            transferDate: new \DateTimeImmutable((string) $data['transfer_at']),
            origin: $data['origin'], destination: $data['destination'], recipient: $data['recipient'], transport: $data['transport'], items: $items,
            grossWeight: (float) $data['gross_weight'], packageCount: isset($data['package_count']) ? (int) $data['package_count'] : null,
            inventoryDocumentId: isset($data['inventory_document_id']) ? (int) $data['inventory_document_id'] : null,
            inventoryTransferId: isset($data['inventory_transfer_id']) ? (int) $data['inventory_transfer_id'] : null,
            billingDocumentId: isset($data['billing_document_id']) ? (int) $data['billing_document_id'] : null,
            relatedGuideId: isset($data['related_guide_id']) ? (int) $data['related_guide_id'] : null,
            externalSender: filled($data['external_sender']['number'] ?? null) || filled($data['external_sender']['issuer_ruc'] ?? null) ? $data['external_sender'] : null,
            exceptionJustification: $data['exception_justification'] ?? null, actorId: $request->user()?->id, notes: $data['notes'] ?? null,
        ));

        return redirect()->route('admin.transport.guides.show', $guide)->with('success', 'GRE preparada sin ejecutar movimientos de inventario.');
    }

    public function show(Request $request, TransportGuide $guide, SecurityScopeService $scope): View
    {
        abort_unless($scope->canAccessTransportGuide($request->user(), $guide), 403);

        return view('transport::guides.show', ['guide' => $guide->load(['items', 'transmissions', 'branch', 'relatedGuide', 'inventoryDocument', 'inventoryTransfer', 'billingDocument'])]);
    }

    public function submit(Request $request, TransportGuide $guide, SecurityScopeService $scope, TransportGuideService $service): RedirectResponse
    {
        abort_unless($scope->canAccessTransportGuide($request->user(), $guide), 403);
        $service->enqueue((int) $guide->organization_id, (int) $guide->id);

        return back()->with('success', 'GRE encolada para emision.');
    }

    public function poll(Request $request, TransportGuide $guide, SecurityScopeService $scope, TransportGuideService $service): RedirectResponse
    {
        abort_unless($scope->canAccessTransportGuide($request->user(), $guide), 403);
        $service->poll((int) $guide->organization_id, (int) $guide->id);

        return back()->with('success', 'Estado GRE consultado.');
    }

    public function downloadXml(Request $request, TransportGuide $guide, SecurityScopeService $scope)
    {
        return $this->download($request, $guide, $scope, 'xml');
    }

    public function downloadCdr(Request $request, TransportGuide $guide, SecurityScopeService $scope)
    {
        return $this->download($request, $guide, $scope, 'cdr');
    }

    private function download(Request $request, TransportGuide $guide, SecurityScopeService $scope, string $type)
    {
        abort_unless($scope->canAccessTransportGuide($request->user(), $guide), 403);
        $disk = $guide->getAttribute($type.'_disk');
        $path = $guide->getAttribute($type.'_path');
        abort_unless(is_string($disk) && is_string($path) && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, ($type === 'cdr' ? 'R-' : '').$guide->formattedNumber().'.'.($type === 'cdr' ? 'zip' : 'xml'));
    }

    private function organizationId(OrganizationContextService $context): int
    {
        $id = (int) $context->currentOrganizationId();
        abort_if($id < 1, 422, 'Selecciona una organizacion activa.');

        return $id;
    }
}
