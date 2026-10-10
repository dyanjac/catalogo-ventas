<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\ManagesPosProducts;
use App\Services\DocumentTotals;
use App\Services\RoundingPolicy;
use App\Support\Decimal;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\UnitMeasure;
use Modules\Sales\Services\CustomerDocumentLookupService;
use Modules\Sales\Services\PosLocationService;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class PosScreen extends Component
{
    use ManagesPosProducts;

    public string $documentType = 'order';

    public int $currentStep = 0;

    public string $currency = 'PEN';

    public string $paymentMethod = 'cash';

    public string $paymentStatus = 'pending';

    public string $idempotencyKey = '';

    public string $taxRate = '0.18';

    public string $discount = '0';

    public string $shipping = '0';

    public string $observations = '';

    public string $lookupFeedback = '';

    public string $lookupFeedbackType = 'muted';

    public array $customer = [
        'name' => '',
        'address' => '',
        'city' => '',
        'phone' => '',
        'document_type' => '',
        'document_number' => '',
    ];

    public array $items = [];

    public string $branchId = '';

    public string $warehouseId = '';

    public function mount(
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext,
    ): void {
        $locations = app(PosLocationService::class);
        $preferredBranch = (int) old('branch_id', $branchContext->currentBranchId(auth()->user()));
        $branches = $locations->branches(auth()->user());
        $this->branchId = (string) ($branches->firstWhere('id', $preferredBranch)?->id ?? $branches->first()?->id ?? '');
        $warehouses = $locations->warehouses((int) $this->branchId);
        $this->warehouseId = (string) ($warehouses->firstWhere('id', (int) old('warehouse_id'))?->id
            ?? $warehouses->first()?->id ?? '');
        $this->loadProductIndex($scopeService);

        $this->documentType = old('document_type', 'order');
        $this->currency = old('currency', config('sales.default_currency', 'PEN'));
        $this->paymentMethod = old('payment_method', 'cash');
        $this->paymentStatus = old('payment_status', 'pending');
        $this->idempotencyKey = (string) old('idempotency_key', Str::uuid());
        $this->taxRate = trim((string) old('tax_rate', config('sales.default_tax_rate', 0.18)));
        $this->discount = trim((string) old('discount', 0));
        $this->shipping = trim((string) old('shipping', 0));
        $this->observations = (string) old('observations', '');
        $this->customer = [
            'name' => (string) old('customer.name', ''),
            'address' => (string) old('customer.address', ''),
            'city' => (string) old('customer.city', ''),
            'phone' => (string) old('customer.phone', ''),
            'document_type' => (string) old('customer.document_type', ''),
            'document_number' => (string) old('customer.document_number', ''),
        ];

        $oldItems = old('items', [
            ['product_id' => '', 'quantity' => '1', 'unit_price' => ''],
        ]);

        $this->items = collect($oldItems)
            ->map(function (array $item): array {
                return [
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'quantity' => trim((string) ($item['quantity'] ?? '1')),
                    'unit_price' => trim((string) ($item['unit_price'] ?? '')),
                ];
            })
            ->values()
            ->all();

        if ($this->items === []) {
            $this->addItem();
        }

        $this->syncDocumentRules();
    }

    public function setDocumentType(string $type): void
    {
        $this->documentType = in_array($type, ['order', 'boleta', 'factura'], true) ? $type : 'order';
        $this->syncDocumentRules();
    }

    public function updatedBranchId(SecurityScopeService $scope): void
    {
        $locations = app(PosLocationService::class);
        $locations->assertBranch(auth()->user(), (int) $this->branchId);
        $this->warehouseId = (string) ($locations->warehouses((int) $this->branchId)->first()?->id ?? '');
        $this->quickStockWarehouseId = $this->warehouseId;
        $this->loadProductIndex($scope);
        $this->resetErrorBag('wizard');
    }

    public function updatedWarehouseId(SecurityScopeService $scope): void
    {
        app(PosLocationService::class)->assertBranch(auth()->user(), (int) $this->branchId);
        if ($this->warehouseId !== '') {
            app(PosLocationService::class)->assertWarehouse((int) $this->branchId, (int) $this->warehouseId);
        }
        $this->loadProductIndex($scope);
        $this->resetErrorBag('wizard');
    }

    public function addItem(): void
    {
        $this->items[] = [
            'product_id' => '',
            'quantity' => '1',
            'unit_price' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function lookupCustomerDocument(CustomerDocumentLookupService $lookupService): void
    {
        $type = trim((string) ($this->customer['document_type'] ?? ''));
        $number = trim((string) ($this->customer['document_number'] ?? ''));

        if ($type === '' || $number === '') {
            $this->lookupFeedback = 'Selecciona tipo y numero de documento antes de consultar.';
            $this->lookupFeedbackType = 'danger';

            return;
        }

        $result = $lookupService->lookup($type, $number);

        if (! ($result['ok'] ?? false)) {
            $this->lookupFeedback = (string) ($result['message'] ?? 'No se pudo consultar el documento.');
            $this->lookupFeedbackType = 'danger';

            return;
        }

        $customer = is_array($result['customer'] ?? null) ? $result['customer'] : [];
        $rawContent = is_array(data_get($result, 'raw.Contenido')) ? data_get($result, 'raw.Contenido') : [];
        $resolvedName = $customer['name'] ?? $result['name'] ?? $rawContent['nombrecompleto'] ?? trim(implode(' ', array_filter([
            $rawContent['prenombres'] ?? null,
            $rawContent['apPrimer'] ?? null,
            $rawContent['apSegundo'] ?? null,
        ])));

        $this->customer['name'] = $resolvedName ?: $this->customer['name'];
        $this->customer['address'] = (string) ($customer['address'] ?? $result['address'] ?? $rawContent['direccion'] ?? $this->customer['address']);
        $this->customer['city'] = (string) ($customer['city'] ?? $result['city'] ?? $rawContent['ubigeo'] ?? $this->customer['city']);
        $this->customer['phone'] = (string) ($customer['phone'] ?? $result['phone'] ?? $rawContent['telefono'] ?? $rawContent['celular'] ?? $this->customer['phone']);
        $this->lookupFeedback = (string) ($result['message'] ?? 'Documento consultado correctamente.');
        $this->lookupFeedbackType = 'success';
    }

    public function goToStep(int $step): void
    {
        $step = max(0, min(2, $step));

        if ($step > $this->currentStep + 1) {
            $step = $this->currentStep + 1;
        }

        if ($step > $this->currentStep && ! $this->canAdvanceFromCurrentStep()) {
            return;
        }

        $this->currentStep = $step;
    }

    public function goNext(): void
    {
        if ($this->currentStep < 2) {
            $this->goToStep($this->currentStep + 1);
        }
    }

    public function goPrev(): void
    {
        if ($this->currentStep > 0) {
            $this->currentStep--;
        }
    }

    public function updatedItems($value, string $key): void
    {
        if (str_ends_with($key, '.product_id')) {
            $index = (int) explode('.', $key)[0];
            $product = $this->getProductById((string) ($this->items[$index]['product_id'] ?? ''));

            $this->items[$index]['unit_price'] = $product
                ? Decimal::unitPriceForInput($product['price'])
                : '';
        }
    }

    public function updatedTaxRate(): void
    {
        $this->resetErrorBag('taxRate');
        if (! preg_match('/^(?:0(?:\.\d{1,4})?|1(?:\.0{1,4})?)$/', trim($this->taxRate))) {
            $this->addError('taxRate', 'La tasa IGV debe estar entre 0 y 1, con hasta cuatro decimales.');
        }
    }

    public function updatedDiscount(): void
    {
        $this->resetErrorBag('discount');
        if ($this->discount !== '' && ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', trim($this->discount))) {
            $this->addError('discount', 'El descuento debe ser positivo y tener como máximo dos decimales.');
        }
    }

    public function updatedShipping(): void
    {
        $this->resetErrorBag('shipping');
        if ($this->shipping !== '' && ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', trim($this->shipping))) {
            $this->addError('shipping', 'El envío debe ser positivo y tener como máximo dos decimales.');
        }
    }

    public function render()
    {
        return view('livewire.admin.pos-screen', [
            'categories' => Category::query()->forCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'unitMeasures' => UnitMeasure::query()->forCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'warehouses' => $this->availableWarehouses(),
            'saleBranches' => app(PosLocationService::class)->branches(auth()->user()),
            'saleWarehouses' => $this->branchId !== '' ? app(PosLocationService::class)->warehouses((int) $this->branchId) : collect(),
            'canCreateProduct' => $this->canCreateProduct(),
            'canAddStock' => $this->canAddStock(),
        ]);
    }

    public function subtotal(): string
    {
        $subtotal = '0';
        foreach ($this->items as $item) {
            if ((string) ($item['product_id'] ?? '') === '' || ! is_numeric($item['quantity'] ?? null) || ! is_numeric($item['unit_price'] ?? null)) {
                continue;
            }
            $subtotal = Decimal::add($subtotal, Decimal::round(Decimal::mul($item['quantity'], $item['unit_price'], 10), 2, $this->roundingMode()), 2);
        }

        return Decimal::round($subtotal, 2);
    }

    public function lineSubtotal(array $item): string
    {
        if (! is_numeric($item['quantity'] ?? null) || ! is_numeric($item['unit_price'] ?? null)) {
            return '0.00';
        }

        return Decimal::round(Decimal::mul($item['quantity'], $item['unit_price'], 10), 2, $this->roundingMode());
    }

    public function itemCount(): string
    {
        return collect($this->items)->reduce(function (string $sum, array $item): string {
            return (string) ($item['product_id'] ?? '') === '' || ! is_numeric($item['quantity'] ?? null)
                ? $sum : Decimal::add($sum, $item['quantity'], 4);
        }, '0');
    }

    public function taxAmount(): string
    {
        return $this->previewTotals()['tax'] ?? '0.00';
    }

    public function totalAmount(): string
    {
        return $this->previewTotals()['total'] ?? '0.00';
    }

    private function roundingMode(): string
    {
        $organizationId = app()->bound('auth') ? (int) (auth()->user()?->organization_id ?? 0) : 0;

        return $organizationId > 0
            ? app(RoundingPolicy::class)->mode($organizationId, $this->branchId !== '' ? (int) $this->branchId : null)
            : 'half_up';
    }

    private function previewTotals(): ?array
    {
        $lines = [];
        foreach ($this->items as $item) {
            if ((string) ($item['product_id'] ?? '') === '' || ! is_numeric($item['quantity'] ?? null) || ! is_numeric($item['unit_price'] ?? null)) {
                continue;
            }
            $lines[] = ['quantity' => $item['quantity'], 'unit_price' => $item['unit_price']];
        }
        $organizationId = app()->bound('auth') ? (int) (auth()->user()?->organization_id ?? 0) : 0;
        if ($organizationId < 1 || $lines === []) {
            return null;
        }

        try {
            return app(DocumentTotals::class)->calculate($lines, $this->discount, $this->shipping, $this->taxRate, $organizationId, $this->branchId !== '' ? (int) $this->branchId : null);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function documentMeta(): array
    {
        return match ($this->documentType) {
            'boleta' => [
                'title' => 'Boleta electronica',
                'help' => 'La boleta necesita cliente y documento valido antes de registrarse.',
                'customer' => 'Para boleta registra tipo y numero de documento del cliente.',
            ],
            'factura' => [
                'title' => 'Factura electronica',
                'help' => 'La factura exige RUC y datos validos del cliente para emitir el comprobante.',
                'customer' => 'Para factura el cliente debe tener documento tipo RUC y numero valido.',
            ],
            default => [
                'title' => 'Pedido POS',
                'help' => 'Registro interno rapido. Puedes completar cliente y pago despues de seleccionar productos.',
                'customer' => 'Para pedido POS basta con un nombre de cliente.',
            ],
        };
    }

    private function syncDocumentRules(): void
    {
        if ($this->documentType === 'factura') {
            $this->customer['document_type'] = 'RUC';
        }

        if ($this->documentType === 'order' && $this->lookupFeedbackType === 'danger') {
            $this->lookupFeedback = '';
            $this->lookupFeedbackType = 'muted';
        }
    }

    private function canAdvanceFromCurrentStep(): bool
    {
        $this->resetErrorBag('wizard');

        if ($this->currentStep === 0) {
            if ($this->branchId === '' || $this->warehouseId === '') {
                $this->addError('wizard', 'Selecciona una sucursal y un almacén antes de continuar.');

                return false;
            }
            $requestedByProduct = [];
            foreach ($this->items as $item) {
                $product = $this->getProductById((string) ($item['product_id'] ?? ''));
                $quantity = trim((string) ($item['quantity'] ?? ''));
                $price = trim((string) ($item['unit_price'] ?? ''));

                if (! $product) {
                    $this->addError('wizard', 'Selecciona un producto en cada ítem o quita las filas vacías.');

                    return false;
                }
                if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $quantity) || Decimal::compare($quantity, '0.0001', 4) < 0) {
                    $this->addError('wizard', 'La cantidad debe ser positiva y tener como máximo cuatro decimales escritos con punto.');

                    return false;
                }
                $requestedByProduct[$product['id']] = Decimal::add($requestedByProduct[$product['id']] ?? 0, $quantity, 4);
                if (($product['tracks_inventory'] ?? false) && Decimal::compare($product['stock'], $requestedByProduct[$product['id']], 4) < 0) {
                    $this->addError('wizard', "Stock insuficiente para {$product['name']} en el almacén seleccionado.");

                    return false;
                }
                if ($price !== '' && ! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/', $price)) {
                    $this->addError('wizard', 'El precio debe tener como máximo seis decimales escritos con punto.');

                    return false;
                }
            }
        }

        if ($this->currentStep === 1) {
            $customerName = trim((string) ($this->customer['name'] ?? ''));
            $documentType = trim((string) ($this->customer['document_type'] ?? ''));
            $documentNumber = trim((string) ($this->customer['document_number'] ?? ''));

            if ($customerName === '') {
                $this->addError('wizard', 'Ingresa el nombre del cliente para continuar.');

                return false;
            }

            if ($this->documentType === 'factura' && ($documentType !== 'RUC' || strlen($documentNumber) !== 11)) {
                $this->addError('wizard', 'Para factura debes registrar un RUC valido de 11 digitos.');

                return false;
            }

            if ($this->documentType === 'boleta' && ($documentType === '' || $documentNumber === '')) {
                $this->addError('wizard', 'Para boleta registra tipo y numero de documento del cliente.');

                return false;
            }
        }

        return true;
    }
}
