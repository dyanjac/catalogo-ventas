<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Modules\Catalog\Entities\Product;
use Modules\Sales\Services\CustomerDocumentLookupService;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class PosScreen extends Component
{
    public string $documentType = 'order';

    public int $currentStep = 0;

    public string $currency = 'PEN';

    public string $paymentMethod = 'cash';

    public string $paymentStatus = 'pending';

    public float $taxRate = 0.18;

    public float $discount = 0.0;

    public float $shipping = 0.0;

    public string $observations = '';

    public string $productSearch = '';

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

    /**
     * @var array<int, array{id:int,name:string,sku:string,stock:int,price:float,label:string}>
     */
    public array $productIndex = [];

    public function mount(SecurityScopeService $scopeService, SecurityBranchContextService $branchContext): void
    {
        $actor = auth()->user();
        $branchId = $branchContext->currentBranchId($actor);

        $this->productIndex = $scopeService->scopeProducts(Product::query(), $actor, 'catalog')
            ->where('is_active', true)
            ->with(['branchStocks' => fn ($query) => $branchId ? $query->where('branch_id', $branchId)->where('is_active', true) : $query])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'sale_price', 'price', 'stock'])
            ->map(function (Product $product) use ($branchId): ?array {
                $price = (float) ($product->sale_price ?? $product->price ?? 0);
                $stock = $branchId
                    ? (int) ($product->branchStocks->first()?->stock ?? 0)
                    : (int) ($product->stock ?? 0);

                if ($stock <= 0) {
                    return null;
                }

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?: 'SIN-SKU'),
                    'stock' => $stock,
                    'price' => round($price, 2),
                    'label' => (string) ($product->name.' ('.($product->sku ?: 'SIN-SKU').')'),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $this->documentType = old('document_type', 'order');
        $this->currency = old('currency', config('sales.default_currency', 'PEN'));
        $this->paymentMethod = old('payment_method', 'cash');
        $this->paymentStatus = old('payment_status', 'pending');
        $this->taxRate = (float) old('tax_rate', config('sales.default_tax_rate', 0.18));
        $this->discount = (float) old('discount', 0);
        $this->shipping = (float) old('shipping', 0);
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
            ['product_id' => '', 'quantity' => 1, 'unit_price' => 0],
        ]);

        $this->items = collect($oldItems)
            ->map(function (array $item): array {
                return [
                    'product_id' => (string) ($item['product_id'] ?? ''),
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
                ];
            })
            ->values()
            ->all();

        if ($this->items === []) {
            $this->addItem();
        }

        $this->syncDocumentRules();
        $this->normalizeItems();
    }

    public function setDocumentType(string $type): void
    {
        $this->documentType = in_array($type, ['order', 'boleta', 'factura'], true) ? $type : 'order';
        $this->syncDocumentRules();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'product_id' => '',
            'quantity' => 1,
            'unit_price' => 0,
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

    public function addItemBySearch(): void
    {
        $product = $this->findProductByTerm($this->productSearch);

        if (! $product) {
            $this->addError('productSearch', 'No se encontro un producto con ese criterio.');
            return;
        }

        $this->resetErrorBag('productSearch');
        $this->items[] = [
            'product_id' => (string) $product['id'],
            'quantity' => 1,
            'unit_price' => $product['price'],
        ];
        $this->productSearch = '';
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

            if ($product && (! isset($this->items[$index]['unit_price']) || (float) $this->items[$index]['unit_price'] <= 0)) {
                $this->items[$index]['unit_price'] = $product['price'];
            }
        }

        $this->normalizeItems();
    }

    public function updatedTaxRate(): void
    {
        $this->taxRate = max(0, (float) $this->taxRate);
    }

    public function updatedDiscount(): void
    {
        $this->discount = max(0, (float) $this->discount);
    }

    public function updatedShipping(): void
    {
        $this->shipping = max(0, (float) $this->shipping);
    }

    public function render()
    {
        return view('livewire.admin.pos-screen');
    }

    public function subtotal(): float
    {
        return round(collect($this->items)->sum(function (array $item): float {
            $productId = (string) ($item['product_id'] ?? '');
            if ($productId === '') {
                return 0;
            }

            return (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
        }), 2);
    }

    public function itemCount(): float
    {
        return round(collect($this->items)->sum(function (array $item): float {
            return (string) ($item['product_id'] ?? '') === '' ? 0 : (float) ($item['quantity'] ?? 0);
        }), 2);
    }

    public function taxAmount(): float
    {
        $base = max($this->subtotal() - $this->discount, 0);

        return round($base * max((float) $this->taxRate, 0), 2);
    }

    public function totalAmount(): float
    {
        $base = max($this->subtotal() - $this->discount, 0);

        return round($base + $this->taxAmount() + max((float) $this->shipping, 0), 2);
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

        if ($this->currentStep === 0 && ! collect($this->items)->contains(fn (array $item) => (string) ($item['product_id'] ?? '') !== '')) {
            $this->addError('wizard', 'Debes seleccionar al menos un producto antes de continuar.');
            return false;
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

    private function normalizeItems(): void
    {
        $this->items = collect($this->items)
            ->map(function (array $item): array {
                $productId = (string) ($item['product_id'] ?? '');
                $product = $this->getProductById($productId);
                $quantity = max((float) ($item['quantity'] ?? 1), 0.01);
                $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);

                if ($product && $unitPrice <= 0) {
                    $unitPrice = $product['price'];
                }

                return [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            })
            ->values()
            ->all();
    }

    private function findProductByTerm(string $term): ?array
    {
        $normalized = mb_strtolower(trim($term));

        if ($normalized === '') {
            return null;
        }

        return collect($this->productIndex)->first(function (array $product) use ($normalized): bool {
            return mb_strtolower($product['label']) === $normalized
                || str_contains(mb_strtolower($product['name']), $normalized)
                || str_contains(mb_strtolower($product['sku']), $normalized);
        });
    }

    private function getProductById(string $id): ?array
    {
        return collect($this->productIndex)->first(fn (array $product): bool => (string) $product['id'] === $id);
    }
}
