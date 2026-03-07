@extends('layouts.admin')

@section('title', 'Detalle producto')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="$product->name">
            <x-slot:actions>
                <x-admin.action-bar>
                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary rounded-pill px-4">Editar</a>
                    <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
                </x-admin.action-bar>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.info-card>
                <div class="mb-4">
                    <div class="col-12">
                        <strong>Imagen principal:</strong><br>
                        <img
                            src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}"
                            alt="{{ $product->name }}"
                            class="img-fluid rounded border mt-2"
                            style="max-height: 240px; object-fit: contain;"
                        >
                    </div>
                </div>

                <x-admin.detail-grid
                    :items="[
                        ['label' => 'SKU', 'value' => $product->sku ?? '-'],
                        ['label' => 'Categoría', 'value' => $product->category?->name ?? '-'],
                        ['label' => 'Unidad', 'value' => $product->unitMeasure?->name ?? '-'],
                        ['label' => 'Precio compra', 'value' => 'S/ ' . number_format((float) ($product->purchase_price ?? 0), 2)],
                        ['label' => 'Precio venta', 'value' => 'S/ ' . number_format((float) ($product->sale_price ?? 0), 2)],
                        ['label' => 'Precio mayor', 'value' => 'S/ ' . number_format((float) ($product->wholesale_price ?? 0), 2)],
                        ['label' => 'Precio promedio', 'value' => 'S/ ' . number_format((float) ($product->average_price ?? 0), 2)],
                        ['label' => 'Stock', 'value' => $product->stock],
                        ['label' => 'Stock mínimo', 'value' => $product->min_stock],
                        ['label' => 'Afectación', 'value' => $product->tax_affectation],
                        ['label' => 'Activo', 'value' => $product->is_active ? 'Sí' : 'No'],
                        ['label' => 'Usa serie', 'value' => $product->uses_series ? 'Sí' : 'No'],
                        ['label' => 'Cuenta', 'value' => $product->account ?? '-'],
                        ['label' => 'Genera asiento', 'value' => $product->requires_accounting_entry ? 'Sí' : 'No'],
                        ['label' => 'Cuenta ingresos', 'value' => $product->account_revenue ?? '-'],
                        ['label' => 'Cuenta por cobrar', 'value' => $product->account_receivable ?? '-'],
                        ['label' => 'Cuenta inventario', 'value' => $product->account_inventory ?? '-'],
                        ['label' => 'Cuenta costo venta', 'value' => $product->account_cogs ?? '-'],
                        ['label' => 'Cuenta impuesto', 'value' => $product->account_tax ?? '-'],
                        ['label' => 'Descripción', 'value' => $product->description ?: '-', 'class' => 'col-12'],
                    ]"
                />
        </x-admin.info-card>
    </div>
</div>
@endsection

