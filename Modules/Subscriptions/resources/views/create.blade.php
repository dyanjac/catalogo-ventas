@extends('layouts.admin')

@section('title', 'Nueva suscripciÃ³n')

@section('content')
<div class="container-fluid py-2"><x-admin.page-header title="Nueva suscripciÃ³n comercial" />
<form method="POST" action="{{ route('admin.subscriptions.store') }}" class="card border"><div class="card-body row g-3">@csrf
    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
    <div class="col-md-4"><label class="form-label">Cliente</label><select name="customer_id" class="form-select" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} Â· {{ $customer->email }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Producto suscripciÃ³n</label><select name="product_id" class="form-select" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Sucursal</label><select name="branch_id" class="form-select"><option value="">Sin sucursal</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Inicio del servicio</label><input type="date" name="service_starts_on" class="form-control" value="{{ now('UTC')->toDateString() }}" required></div>
    <div class="col-md-3"><label class="form-label">Ciclo</label><select name="billing_cycle_months" class="form-select"><option value="1">Mensual</option><option value="3">Trimestral</option><option value="12">Anual</option></select></div>
    <div class="col-md-2"><label class="form-label">Moneda</label><input name="currency" class="form-control text-uppercase" value="PEN" maxlength="3" required></div>
    <div class="col-md-2"><label class="form-label">Subtotal</label><input type="number" step="0.01" min="0.01" name="recurring_subtotal" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label">Impuesto</label><input type="number" step="0.01" min="0" name="recurring_tax" class="form-control" value="0"></div>
    <div class="col-md-6"><label class="form-label">Comprobante anticipado emitido</label><select name="billing_document_id" class="form-select" required><option value="">Seleccione...</option>@foreach($billingDocuments as $document)<option value="{{ $document->id }}">{{ $document->series }}-{{ $document->number }} Â· {{ $document->currency }} {{ number_format($document->total, 2) }}</option>@endforeach</select><small class="text-muted">Debe contener el mismo producto e importes del contrato.</small></div>
    <div class="col-12"><button class="btn btn-primary rounded-pill px-4">Activar y programar</button> <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-light border rounded-pill">Cancelar</a></div>
</div></form></div>
@endsection
