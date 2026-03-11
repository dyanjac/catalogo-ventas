@extends('layouts.admin')

@section('title', 'Detalle de comprobante electrónico')

@php
    $payload = is_array($document->request_payload) ? $document->request_payload : [];
    $payloadCustomer = is_array(data_get($payload, 'customer')) ? data_get($payload, 'customer') : [];
    $payloadTotals = is_array(data_get($payload, 'totals')) ? data_get($payload, 'totals') : [];
    $payloadItems = collect(data_get($payload, 'items', []))
        ->filter(fn ($item) => is_array($item));

    $items = $payloadItems->isNotEmpty()
        ? $payloadItems->map(function (array $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $lineSubtotal = (float) ($item['line_subtotal'] ?? ($quantity * $unitPrice));
            $lineTax = (float) ($item['tax_amount'] ?? 0);
            $lineTotal = (float) ($item['line_total'] ?? ($lineSubtotal + $lineTax));

            return [
                'sku' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['name'] ?? 'Item sin descripcion'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
        })
        : $document->order?->items->map(function ($item) {
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $lineSubtotal = (float) ($item->subtotal ?? ($quantity * $unitPrice));
            $lineTax = (float) ($item->tax_amount ?? 0);
            $lineTotal = (float) ($item->total ?? ($lineSubtotal + $lineTax));

            return [
                'sku' => (string) ($item->product->sku ?? ''),
                'name' => (string) ($item->product->name ?? 'Item sin descripcion'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
        }) ?? collect();

    $statusValue = strtolower((string) $document->status);
    $statusClass = match ($statusValue) {
        'issued', 'accepted' => 'bg-success',
        'error', 'rejected', 'accepted_with_observation', 'accepted-observation', 'accepted_observation' => 'bg-danger',
        'queued' => 'bg-warning text-dark',
        default => 'bg-secondary',
    };

    $hasXml = (bool) ($document->xmlFile() || $document->xml_path || data_get($document->request_payload, 'xml_path'));
    $hasCdr = (bool) ($document->cdrFile() || data_get($document->response_payload, 'cdr_path') || data_get($document->response_payload, 'cdr_base64') || data_get($document->response_payload, 'body.cdr_base64') || data_get($document->response_payload, 'body.cdrZipBase64'));
@endphp

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="'Detalle ' . $document->series . '-' . $document->number">
            <x-slot:actions>
                <a href="{{ route('admin.billing.documents.history', $document) }}" class="btn btn-light border rounded-pill px-4">Historial</a>
                <form method="POST" action="{{ route('admin.billing.documents.redeclare', $document) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning border rounded-pill px-4" onclick="return confirm('¿Re-declarar este comprobante al proveedor configurado?')">
                        Re-declarar
                    </button>
                </form>
                <a href="{{ $hasXml ? route('admin.billing.documents.download.xml', $document) : '#' }}" class="btn btn-light border rounded-pill px-4 {{ $hasXml ? '' : 'disabled' }}">XML</a>
                <a href="{{ $hasCdr ? route('admin.billing.documents.download.cdr', $document) : '#' }}" class="btn btn-light border rounded-pill px-4 {{ $hasCdr ? '' : 'disabled' }}">CDR</a>
                <a href="{{ route('admin.billing.documents.download.pdf', $document) }}" class="btn btn-light border rounded-pill px-4">PDF</a>
                <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card border border-secondary rounded-3 h-100">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Cabecera del comprobante</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Tipo</dt>
                            <dd class="col-7">{{ strtoupper($document->document_type) }}</dd>

                            <dt class="col-5">Serie / Numero</dt>
                            <dd class="col-7">{{ $document->series }}-{{ $document->number }}</dd>

                            <dt class="col-5">Proveedor</dt>
                            <dd class="col-7">{{ strtoupper($document->provider) }}</dd>

                            <dt class="col-5">Estado</dt>
                            <dd class="col-7"><span class="badge {{ $statusClass }}">{{ strtoupper($document->status) }}</span></dd>

                            <dt class="col-5">Emision</dt>
                            <dd class="col-7">{{ $document->issue_date?->format('d/m/Y') ?? '-' }}</dd>

                            <dt class="col-5">Moneda</dt>
                            <dd class="col-7">{{ $document->currency }}</dd>

                            <dt class="col-5">Pedido</dt>
                            <dd class="col-7">{{ $document->order_id ? '#'.$document->order_id : '-' }}</dd>

                            <dt class="col-5">XML Hash</dt>
                            <dd class="col-7 text-break">{{ $document->xml_hash ?: '-' }}</dd>

                            <dt class="col-5">Ticket SUNAT</dt>
                            <dd class="col-7">{{ $document->sunat_ticket ?: '-' }}</dd>

                            <dt class="col-5">CDR</dt>
                            <dd class="col-7">{{ $document->sunat_cdr_code ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border border-secondary rounded-3 h-100">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Datos del cliente</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Documento</dt>
                            <dd class="col-7">{{ $document->customer_document_type }} {{ $document->customer_document_number }}</dd>

                            <dt class="col-5">Nombre</dt>
                            <dd class="col-7 text-break">{{ data_get($payloadCustomer, 'name', data_get($document->order?->shipping_address, 'name', '-')) }}</dd>

                            <dt class="col-5">Direccion</dt>
                            <dd class="col-7 text-break">{{ data_get($payloadCustomer, 'address', data_get($document->order?->shipping_address, 'address', '-')) }}</dd>

                            <dt class="col-5">Ciudad</dt>
                            <dd class="col-7">{{ data_get($payloadCustomer, 'city', data_get($document->order?->shipping_address, 'city', '-')) }}</dd>

                            <dt class="col-5">Telefono</dt>
                            <dd class="col-7">{{ data_get($payloadCustomer, 'phone', data_get($document->order?->shipping_address, 'phone', '-')) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border border-secondary rounded-3 h-100">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Totales</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end">{{ number_format((float) ($payloadTotals['subtotal'] ?? $document->subtotal), 2) }} {{ $document->currency }}</dd>

                            <dt class="col-6">Descuento</dt>
                            <dd class="col-6 text-end">{{ number_format((float) ($payloadTotals['discount'] ?? 0), 2) }} {{ $document->currency }}</dd>

                            <dt class="col-6">Impuesto</dt>
                            <dd class="col-6 text-end">{{ number_format((float) ($payloadTotals['tax'] ?? $document->tax), 2) }} {{ $document->currency }}</dd>

                            <dt class="col-6">Envio</dt>
                            <dd class="col-6 text-end">{{ number_format((float) ($payloadTotals['shipping'] ?? 0), 2) }} {{ $document->currency }}</dd>

                            <dt class="col-6">Total</dt>
                            <dd class="col-6 text-end font-weight-bold">{{ number_format((float) ($payloadTotals['total'] ?? $document->total), 2) }} {{ $document->currency }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border border-secondary rounded-3 mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Items del comprobante</h3>
                <small class="text-muted">
                    Fuente:
                    {{ $payloadItems->isNotEmpty() ? 'payload emitido' : ($document->order?->items?->isNotEmpty() ? 'pedido asociado' : 'sin detalle') }}
                </small>
            </div>
            <div class="card-body p-0">
                @if($items->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>SKU</th>
                                    <th>Descripcion</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">P. Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Impuesto</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item['sku'] !== '' ? $item['sku'] : '-' }}</td>
                                        <td>{{ $item['name'] }}</td>
                                        <td class="text-end">{{ number_format((float) $item['quantity'], 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item['unit_price'], 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item['line_subtotal'], 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item['tax_amount'], 2) }}</td>
                                        <td class="text-end font-weight-bold">{{ number_format((float) $item['line_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5" class="text-end">Totales</th>
                                    <th class="text-end">{{ number_format((float) $document->subtotal, 2) }}</th>
                                    <th class="text-end">{{ number_format((float) $document->tax, 2) }}</th>
                                    <th class="text-end">{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">Este comprobante no tiene detalle de items disponible.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
