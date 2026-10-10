<?php

namespace App\Livewire\Admin\Concerns;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryBalanceReadService;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Sales\Services\PosLocationService;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityScopeService;

trait ManagesPosProducts
{
    public string $productSearch = '';

    public bool $advancedSearchOpen = false;

    public array $advancedFilters = [
        'term' => '', 'category_id' => '', 'stock' => 'available',
        'min_price' => '', 'max_price' => '', 'brand' => '', 'sku' => '', 'description' => '',
    ];

    public bool $quickProductOpen = false;

    public int $quickProductStep = 1;

    public array $quickProduct = [
        'name' => '', 'sku' => '', 'brand' => '', 'description' => '',
        'category_id' => '', 'unit_measure_id' => '', 'product_type' => 'bien_fisico',
        'sale_price' => '', 'purchase_price' => '',
    ];

    #[Locked]
    public ?int $quickProductId = null;

    public string $quickStockWarehouseId = '';

    public string $quickStockQuantity = '';

    public string $quickStockUnitCost = '';

    #[Locked]
    public string $quickStockKey = '';

    public string $productFeedback = '';

    /**
     * @var array<int, array{id:int,name:string,sku:string,brand:string,description:string,category_id:int,stock:int,price:float,label:string,tracks_inventory:bool}>
     */
    public array $productIndex = [];

    public function addItemBySearch(): void
    {
        $product = $this->findProductByTerm($this->productSearch);

        if (! $product) {
            $this->addError('productSearch', 'No se encontro un producto con ese criterio.');

            return;
        }

        $this->selectProduct($product['id']);
    }

    public function selectProduct(int $productId): void
    {
        $product = $this->getProductById((string) $productId);

        if (! $product || $product['stock'] <= 0) {
            $this->addError('productSearch', 'Este producto no tiene stock disponible en el almacén seleccionado.');

            return;
        }

        $this->resetErrorBag('productSearch');
        $newItem = [
            'product_id' => (string) $product['id'],
            'quantity' => '1',
            'unit_price' => number_format($product['price'], 2, '.', ''),
        ];
        $emptyIndex = collect($this->items)->search(fn (array $item): bool => (string) ($item['product_id'] ?? '') === '');
        if ($emptyIndex === false) {
            $this->items[] = $newItem;
        } else {
            $this->items[$emptyIndex] = $newItem;
        }
        $this->productSearch = '';
        $this->advancedSearchOpen = false;
        $this->productFeedback = '';
    }

    public function productSuggestions(): array
    {
        $term = mb_strtolower(trim($this->productSearch));
        if ($term === '') {
            return [];
        }

        return collect($this->productIndex)
            ->filter(fn (array $product): bool => str_contains(mb_strtolower($product['name']), $term)
                || str_contains(mb_strtolower($product['sku']), $term)
                || str_contains(mb_strtolower($product['brand']), $term))
            ->sortBy(fn (array $product): int => $product['stock'] > 0 ? 0 : 1)
            ->take(8)
            ->values()
            ->all();
    }

    public function openAdvancedSearch(): void
    {
        $this->advancedFilters['term'] = $this->productSearch;
        $this->advancedSearchOpen = true;
    }

    public function closeAdvancedSearch(): void
    {
        $this->advancedSearchOpen = false;
    }

    public function advancedResults(): array
    {
        $filters = $this->advancedFilters;
        $terms = ['term' => ['name', 'sku', 'brand', 'description'], 'brand' => ['brand'], 'sku' => ['sku'], 'description' => ['description']];

        return collect($this->productIndex)
            ->filter(function (array $product) use ($filters, $terms): bool {
                if ($filters['category_id'] !== '' && $product['category_id'] !== (int) $filters['category_id']) {
                    return false;
                }
                if ($filters['stock'] === 'available' && $product['stock'] <= 0) {
                    return false;
                }
                if ($filters['stock'] === 'out' && $product['stock'] > 0) {
                    return false;
                }
                foreach ($terms as $filter => $fields) {
                    $needle = mb_strtolower(trim((string) ($filters[$filter] ?? '')));
                    if ($needle === '') {
                        continue;
                    }
                    if (! collect($fields)->contains(fn (string $field): bool => str_contains(mb_strtolower($product[$field]), $needle))) {
                        return false;
                    }
                }
                $min = trim((string) ($filters['min_price'] ?? ''));
                $max = trim((string) ($filters['max_price'] ?? ''));

                return ($min === '' || (is_numeric($min) && $product['price'] >= (float) $min))
                    && ($max === '' || (is_numeric($max) && $product['price'] <= (float) $max));
            })
            ->take(60)
            ->values()
            ->all();
    }

    public function openQuickProduct(): void
    {
        abort_unless($this->canCreateProduct(), 403);
        $this->resetErrorBag();
        $this->quickProduct = [
            'name' => trim($this->productSearch ?: (string) $this->advancedFilters['term']),
            'sku' => '', 'brand' => '', 'description' => '',
            'category_id' => '', 'unit_measure_id' => '', 'product_type' => ProductType::PhysicalGood->value,
            'sale_price' => '', 'purchase_price' => '',
        ];
        $this->quickProductId = null;
        $this->quickProductStep = 1;
        $this->quickProductOpen = true;
        $this->advancedSearchOpen = false;
        $this->productFeedback = '';
    }

    public function closeQuickProduct(): void
    {
        $this->quickProductOpen = false;
        if ($this->quickProductId && $this->quickProductStep === 2) {
            $this->productFeedback = 'El producto quedó guardado. La venta sigue disponible para continuar.';
        }
    }

    public function saveQuickProduct(
        OrganizationContextService $organization,
        SecurityScopeService $scope,
    ): void {
        abort_unless($this->canCreateProduct(), 403);
        if ($organization->isSuspended()) {
            $this->addError('quickProduct.name', 'La organización está suspendida.');

            return;
        }

        foreach (['name', 'sku', 'brand', 'description', 'sale_price', 'purchase_price'] as $field) {
            $this->quickProduct[$field] = trim((string) ($this->quickProduct[$field] ?? ''));
        }

        $organizationId = (int) $organization->currentOrganizationId();
        $validated = $this->validate([
            'quickProduct.name' => ['required', 'string', 'max:190'],
            'quickProduct.sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->where('organization_id', $organizationId)],
            'quickProduct.brand' => ['nullable', 'string', 'max:120'],
            'quickProduct.description' => ['nullable', 'string', 'max:2000'],
            'quickProduct.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('organization_id', $organizationId)],
            'quickProduct.unit_measure_id' => ['required', 'integer', Rule::exists('unit_measures', 'id')->where('organization_id', $organizationId)],
            'quickProduct.product_type' => ['required', Rule::in([ProductType::PhysicalGood->value, ProductType::Service->value])],
            'quickProduct.sale_price' => ['required', 'regex:/^\\d{1,8}(?:\\.\\d{1,2})?$/'],
            'quickProduct.purchase_price' => ['nullable', 'regex:/^\\d{1,8}(?:\\.\\d{1,2})?$/'],
        ])['quickProduct'];

        $name = trim($validated['name']);
        $sku = trim((string) ($validated['sku'] ?? ''));
        $baseSlug = Str::slug($name) ?: 'producto';
        $slug = $baseSlug;
        $suffix = 2;
        while (Product::withTrashed()->where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }
        if ($sku === '') {
            do {
                $sku = 'PRD-'.Str::upper(Str::random(8));
            } while (Product::withTrashed()->where('organization_id', $organizationId)->where('sku', $sku)->exists());
        }

        $branchId = (int) $this->branchId;
        app(PosLocationService::class)->assertBranch(auth()->user(), $branchId);
        $product = DB::transaction(function () use ($validated, $organizationId, $branchId, $name, $sku, $slug): Product {
            $product = Product::query()->create([
                'organization_id' => $organizationId,
                'category_id' => (int) $validated['category_id'],
                'unit_measure_id' => (int) $validated['unit_measure_id'],
                'name' => $name,
                'sku' => $sku,
                'slug' => $slug,
                'brand' => trim((string) ($validated['brand'] ?? '')) ?: null,
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'product_type' => $validated['product_type'],
                'tax_affectation' => 'Gravado',
                'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
                'requires_accounting_entry' => true,
                'sale_price' => $validated['sale_price'],
                'purchase_price' => trim((string) ($validated['purchase_price'] ?? '')) ?: null,
                'price' => $validated['sale_price'],
                'stock' => 0,
                'min_stock' => 0,
                'is_active' => true,
            ]);
            if ($branchId) {
                ProductBranchStock::query()->firstOrCreate(
                    ['organization_id' => $organizationId, 'product_id' => $product->id, 'branch_id' => $branchId],
                    ['stock' => 0, 'min_stock' => 0, 'is_active' => true],
                );
            }

            return $product;
        });

        $this->quickProductId = (int) $product->id;
        $this->loadProductIndex($scope);
        if (! $product->tracksInventory()) {
            $this->quickProductOpen = false;
            $this->selectProduct((int) $product->id);
            $this->productFeedback = 'Producto creado y añadido a la venta.';

            return;
        }

        $this->prepareStockStep($product);
        $this->productFeedback = 'Producto creado. Puedes cargar stock ahora o continuar con la venta actual.';
    }

    public function openStockForProduct(int $productId): void
    {
        abort_unless($this->canAddStock(), 403);
        $item = $this->getProductById((string) $productId);
        abort_unless($item && $item['tracks_inventory'] && $item['stock'] <= 0, 404);
        $product = Product::query()->forCurrentOrganization()->findOrFail($productId);
        $this->quickProduct['name'] = (string) $product->name;
        $this->prepareStockStep($product);
        $this->quickProductOpen = true;
        $this->advancedSearchOpen = false;
    }

    public function skipQuickStock(): void
    {
        $this->quickProductOpen = false;
        $this->productFeedback = 'El producto quedó creado. Para venderlo, primero carga stock.';
    }

    public function saveQuickStock(
        OrganizationContextService $organization,
        SecurityScopeService $scope,
        InventoryDocumentService $documents,
    ): void {
        abort_unless($this->canAddStock(), 403);
        $product = Product::query()->forCurrentOrganization()->findOrFail($this->quickProductId);
        abort_unless($product->tracksInventory() && $scope->canAccessProduct(auth()->user(), $product, 'catalog'), 403);
        $branchId = (int) $this->branchId;
        app(PosLocationService::class)->assertBranch(auth()->user(), $branchId);

        $this->quickStockQuantity = trim($this->quickStockQuantity);
        $this->quickStockUnitCost = trim($this->quickStockUnitCost);

        $validated = $this->validate([
            'quickStockWarehouseId' => ['required', 'integer', Rule::exists('inventory_warehouses', 'id')
                ->where('organization_id', $organization->currentOrganizationId())
                ->where('branch_id', $branchId)->where('is_active', true)],
            'quickStockQuantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'quickStockUnitCost' => ['required', 'regex:/^\\d{1,8}(?:\\.\\d{1,4})?$/', 'numeric', 'gt:0'],
        ]);
        $warehouse = InventoryWarehouse::query()->forCurrentOrganization()->findOrFail((int) $validated['quickStockWarehouseId']);
        abort_unless($scope->canAccessInventoryWarehouse(auth()->user(), $warehouse, 'inventory'), 403);
        $this->quickStockKey = $this->quickStockKey ?: (string) Str::uuid();

        try {
            $document = $documents->createDraft([
                'organization_id' => $organization->currentOrganizationId(),
                'idempotency_key' => $this->quickStockKey,
                'document_type' => 'inbound',
                'branch_id' => $branchId,
                'warehouse_id' => (int) $warehouse->id,
                'reason' => 'Carga rápida desde POS',
                'issued_at' => now(),
                'created_by' => auth()->id(),
                'items' => [[
                    'product_id' => (int) $product->id,
                    'quantity' => (int) $validated['quickStockQuantity'],
                    'unit_cost' => round((float) $validated['quickStockUnitCost'], 4),
                ]],
            ]);
            $documents->confirm($document->id, auth()->id());
        } catch (ValidationException $exception) {
            $this->addError('quickStock', collect($exception->errors())->flatten()->first() ?: 'No se pudo cargar stock.');

            return;
        }

        $this->warehouseId = (string) $warehouse->id;
        $this->loadProductIndex($scope);
        $this->quickProductOpen = false;
        $this->selectProduct((int) $product->id);
        $this->productFeedback = 'Stock cargado y producto añadido a la venta.';
    }

    private function prepareStockStep(Product $product): void
    {
        $this->resetErrorBag();
        $this->quickProductId = (int) $product->id;
        $this->quickProductStep = 2;
        $this->quickStockKey = (string) Str::uuid();
        $this->quickStockQuantity = '';
        $this->quickStockUnitCost = (float) $product->purchase_price > 0
            ? number_format((float) $product->purchase_price, 2, '.', '') : '';
        $this->quickStockWarehouseId = (string) ($this->availableWarehouses()->firstWhere('id', (int) $this->warehouseId)?->id
            ?? $this->availableWarehouses()->first()?->id ?? '');
    }

    private function canCreateProduct(): bool
    {
        $authorization = app(SecurityAuthorizationService::class);

        return $authorization->canAccessModule(auth()->user(), 'catalog')
            && $authorization->hasPermission(auth()->user(), 'catalog.products.create');
    }

    private function canAddStock(): bool
    {
        $authorization = app(SecurityAuthorizationService::class);

        return $authorization->canAccessModule(auth()->user(), 'inventory')
            && $authorization->hasPermission(auth()->user(), 'inventory.documents.create')
            && $authorization->hasPermission(auth()->user(), 'inventory.documents.confirm');
    }

    private function availableWarehouses()
    {
        if (! $this->canAddStock()) {
            return collect();
        }
        $branchId = (int) $this->branchId;
        if (! $branchId) {
            return collect();
        }
        $scope = app(SecurityScopeService::class);

        return InventoryWarehouse::query()->forCurrentOrganization()
            ->where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (InventoryWarehouse $warehouse): bool => $scope->canAccessInventoryWarehouse(auth()->user(), $warehouse, 'inventory'))
            ->values();
    }

    private function loadProductIndex(SecurityScopeService $scopeService): void
    {
        $actor = auth()->user();
        $branchId = (int) $this->branchId;
        $warehouseId = (int) $this->warehouseId;
        $organizationId = (int) app(OrganizationContextService::class)->currentOrganizationId();
        $usesLedger = $organizationId > 0 && app(InventoryBalanceReadService::class)->usesLedger($organizationId);
        $ledgerStock = $organizationId > 0 && $warehouseId > 0
            ? InventoryBalance::query()->where('organization_id', $organizationId)
                ->where('branch_id', $branchId)->where('warehouse_id', $warehouseId)
                ->get(['product_id', 'physical_stock', 'reserved_stock', 'is_active'])->keyBy('product_id')
            : collect();

        $warehouseStock = $warehouseId > 0
            ? ProductWarehouseStock::query()->where('organization_id', $organizationId)
                ->where('branch_id', $branchId)->where('warehouse_id', $warehouseId)
                ->where('is_active', true)->pluck('stock', 'product_id')
            : collect();

        $this->productIndex = $scopeService->scopeProducts(Product::query()->forCurrentOrganization(), $actor, 'catalog')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'brand', 'description', 'category_id', 'sale_price', 'price', 'stock', 'product_type'])
            ->map(function (Product $product) use ($ledgerStock, $warehouseStock, $usesLedger): array {
                $tracks = $product->tracksInventory();
                $balance = $ledgerStock->get($product->id);
                $stock = $balance
                    ? ($balance->is_active ? $balance->availableStock() : 0)
                    : ($usesLedger ? 0 : (int) ($warehouseStock->get($product->id) ?? 0));

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?: 'SIN-SKU'),
                    'brand' => (string) ($product->brand ?? ''),
                    'description' => (string) ($product->description ?? ''),
                    'category_id' => (int) $product->category_id,
                    'stock' => $tracks ? $stock : 999999,
                    'price' => round((float) ($product->sale_price ?? $product->price ?? 0), 2),
                    'tracks_inventory' => $tracks,
                    'label' => (string) ($product->name.' ('.($product->sku ?: 'SIN-SKU').')'),
                ];
            })->all();
    }

    private function findProductByTerm(string $term): ?array
    {
        $normalized = mb_strtolower(trim($term));

        if ($normalized === '') {
            return null;
        }

        return collect($this->productIndex)
            ->filter(fn (array $product): bool => $product['stock'] > 0)
            ->first(fn (array $product): bool => mb_strtolower($product['label']) === $normalized
                || mb_strtolower($product['sku']) === $normalized)
            ?? collect($this->productSuggestions())->first(fn (array $product): bool => $product['stock'] > 0);
    }

    private function getProductById(string $id): ?array
    {
        return collect($this->productIndex)->first(fn (array $product): bool => (string) $product['id'] === $id);
    }
}
