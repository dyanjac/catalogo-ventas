@extends('layouts.admin')

@section('title', 'Nueva GRE')

@section('content')
<div class="py-2">
    <x-admin.page-header title="Preparar guia de remision">
        <x-slot:actions><a href="{{ route('admin.transport.guides.index') }}" class="btn btn-outline-secondary">Volver</a></x-slot:actions>
    </x-admin.page-header>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('admin.transport.guides.store') }}" class="card border-0 shadow-sm"><div class="card-body">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Sucursal</label><select name="branch_id" class="form-select" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Tipo GRE</label><select name="guide_type" class="form-select"><option value="sender">Remitente</option><option value="carrier" @selected(old('guide_type') === 'carrier')>Transportista</option></select></div>
            <div class="col-md-3"><label class="form-label">Motivo</label><select name="reason_code" class="form-select">@foreach($reasons as $code => $label)<option value="{{ $code }}" @selected(old('reason_code', '01') === $code)>{{ $code }} - {{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Modalidad</label><select name="transport_mode" class="form-select"><option value="02">Privado</option><option value="01" @selected(old('transport_mode') === '01')>Publico</option></select></div>
            <div class="col-md-3"><label class="form-label">Inicio de traslado</label><input type="datetime-local" name="transfer_at" class="form-control" value="{{ old('transfer_at', now()->addHour()->format('Y-m-d\TH:i')) }}" required></div>
            <div class="col-md-3"><label class="form-label">Peso bruto KGM</label><input type="number" step="0.001" min="0.001" name="gross_weight" class="form-control" value="{{ old('gross_weight', 1) }}" required></div>
            <div class="col-md-3"><label class="form-label">Bultos</label><input type="number" min="1" name="package_count" class="form-control" value="{{ old('package_count') }}"></div>
        </div>
        <hr><h5>Puntos del traslado</h5><div class="row g-3">
            <div class="col-md-2"><label class="form-label">Ubigeo partida</label><input name="origin[ubigeo]" class="form-control" value="{{ old('origin.ubigeo') }}" required></div>
            <div class="col-md-4"><label class="form-label">Direccion partida</label><input name="origin[address]" class="form-control" value="{{ old('origin.address') }}" required></div>
            <div class="col-md-2"><label class="form-label">Ubigeo llegada</label><input name="destination[ubigeo]" class="form-control" value="{{ old('destination.ubigeo') }}" required></div>
            <div class="col-md-4"><label class="form-label">Direccion llegada</label><input name="destination[address]" class="form-control" value="{{ old('destination.address') }}" required></div>
        </div>
        <hr><h5>Destinatario</h5><div class="row g-3">
            <div class="col-md-2"><label class="form-label">Tipo documento</label><input name="recipient[document_type]" class="form-control" value="{{ old('recipient.document_type', '6') }}" required></div>
            <div class="col-md-3"><label class="form-label">Numero</label><input name="recipient[document_number]" class="form-control" value="{{ old('recipient.document_number') }}" required></div>
            <div class="col-md-7"><label class="form-label">Nombre / razon social</label><input name="recipient[name]" class="form-control" value="{{ old('recipient.name') }}" required></div>
        </div>
        <hr><h5>Transporte</h5><p class="text-muted small">Para transporte privado completa vehiculo y conductor. Para publico completa transportista.</p><div class="row g-3">
            <div class="col-md-3"><label class="form-label">Placa</label><input name="transport[vehicle_plate]" class="form-control" value="{{ old('transport.vehicle_plate') }}"></div>
            <div class="col-md-3"><label class="form-label">Documento conductor</label><input name="transport[driver_document_number]" class="form-control" value="{{ old('transport.driver_document_number') }}"></div>
            <div class="col-md-3"><label class="form-label">Nombre conductor</label><input name="transport[driver_name]" class="form-control" value="{{ old('transport.driver_name') }}"></div>
            <div class="col-md-3"><label class="form-label">Licencia</label><input name="transport[driver_license]" class="form-control" value="{{ old('transport.driver_license') }}"></div>
            <div class="col-md-4"><label class="form-label">RUC transportista</label><input name="transport[carrier_document_number]" class="form-control" value="{{ old('transport.carrier_document_number') }}"></div>
            <div class="col-md-5"><label class="form-label">Razon social transportista</label><input name="transport[carrier_name]" class="form-control" value="{{ old('transport.carrier_name') }}"></div>
            <div class="col-md-3"><label class="form-label">Registro MTC</label><input name="transport[mtc_registration]" class="form-control" value="{{ old('transport.mtc_registration') }}"></div>
        </div>
        <hr><h5>Vinculos internos opcionales</h5><div class="row g-3">
            <div class="col-md-4"><label class="form-label">Documento de inventario</label><select name="inventory_document_id" class="form-select"><option value="">Sin vinculo</option>@foreach($inventoryDocuments as $doc)<option value="{{ $doc->id }}">{{ $doc->code }} ({{ $doc->document_type->value }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Transferencia</label><select name="inventory_transfer_id" class="form-select"><option value="">Sin vinculo</option>@foreach($inventoryTransfers as $transfer)<option value="{{ $transfer->id }}">{{ $transfer->code }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Factura / boleta</label><select name="billing_document_id" class="form-select"><option value="">Sin comprobante previo</option>@foreach($billingDocuments as $doc)<option value="{{ $doc->id }}">{{ $doc->series }}-{{ $doc->number }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">GRE remitente relacionada</label><select name="related_guide_id" class="form-select"><option value="">No aplica</option>@foreach($senderGuides as $sender)<option value="{{ $sender->id }}">{{ $sender->formattedNumber() }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Justificacion de excepcion</label><input name="exception_justification" class="form-control" value="{{ old('exception_justification') }}"></div>
            <div class="col-12"><small class="text-muted">Para una GRE transportista tambien puede registrar una GRE remitente externa:</small></div>
            <div class="col-md-4"><label class="form-label">Numero GRE externa</label><input name="external_sender[number]" class="form-control" placeholder="T001-00000001" value="{{ old('external_sender.number') }}"></div>
            <div class="col-md-4"><label class="form-label">RUC emisor externo</label><input name="external_sender[issuer_ruc]" class="form-control" maxlength="11" value="{{ old('external_sender.issuer_ruc') }}"></div>
            <input type="hidden" name="external_sender[document_type]" value="09">
        </div>
        <hr><div class="d-flex justify-content-between align-items-center"><h5>Bienes</h5><button type="button" id="add-item" class="btn btn-sm btn-outline-primary">Agregar linea</button></div>
        <div id="items"><div class="row g-2 item-row mb-2">
            <div class="col-md-3"><select name="items[0][product_id]" class="form-select"><option value="">Bien externo</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} - {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input name="items[0][code]" class="form-control" placeholder="Codigo" required></div>
            <div class="col-md-3"><input name="items[0][description]" class="form-control" placeholder="Descripcion" required></div>
            <div class="col-md-2"><input type="number" step="0.0001" min="0.0001" name="items[0][quantity]" class="form-control" placeholder="Cantidad" required></div>
            <div class="col-md-2"><input name="items[0][unit_code]" class="form-control" value="NIU" required></div>
        </div></div>
        <div class="mt-3"><label class="form-label">Notas</label><textarea name="notes" class="form-control">{{ old('notes') }}</textarea></div>
    </div><div class="card-footer text-end"><button class="btn btn-primary">Preparar GRE</button></div></form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('add-item')?.addEventListener('click', () => {
    const container = document.getElementById('items');
    const row = container.querySelector('.item-row').cloneNode(true);
    const index = container.querySelectorAll('.item-row').length;
    row.querySelectorAll('input,select').forEach(field => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        if (field.tagName === 'INPUT' && !field.name.endsWith('[unit_code]')) field.value = '';
    });
    container.appendChild(row);
});
</script>
@endpush
