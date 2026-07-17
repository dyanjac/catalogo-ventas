@extends('layouts.admin')

@section('title', 'GRE '.$guide->formattedNumber())

@php($transportAuthorization = app(\Modules\Security\Services\SecurityAuthorizationService::class))

@section('content')
<div class="py-2">
    <x-admin.page-header title="GRE {{ $guide->formattedNumber() }}">
        <x-slot:actions>
            <a href="{{ route('admin.transport.guides.index') }}" class="btn btn-outline-secondary">Volver</a>
            @if(in_array($guide->status->value, ['ready','error']) && $transportAuthorization->hasPermission(auth()->user(), 'transport.guides.submit'))
                <form method="POST" action="{{ route('admin.transport.guides.submit', $guide) }}" class="d-inline">@csrf<button class="btn btn-primary">Enviar</button></form>
            @endif
            @if($guide->status->value === 'submitted' && $transportAuthorization->hasPermission(auth()->user(), 'transport.guides.poll'))
                <form method="POST" action="{{ route('admin.transport.guides.poll', $guide) }}" class="d-inline">@csrf<button class="btn btn-primary">Consultar SUNAT</button></form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>
    <div class="row g-3"><div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body">
        <dl class="row"><dt class="col-sm-4">Tipo</dt><dd class="col-sm-8">{{ $guide->guide_type->value }}</dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8">{{ $guide->status->value }}</dd><dt class="col-sm-4">Motivo</dt><dd class="col-sm-8">{{ $guide->reason_code }} - {{ config('transport.reasons.'.$guide->reason_code) }}</dd><dt class="col-sm-4">Inicio traslado</dt><dd class="col-sm-8">{{ $guide->transfer_at?->format('d/m/Y H:i') }}</dd><dt class="col-sm-4">Ticket</dt><dd class="col-sm-8">{{ $guide->provider_ticket ?: '-' }}</dd></dl>
        @if($guide->external_sender_snapshot)<p><strong>GRE remitente externa:</strong> {{ data_get($guide->external_sender_snapshot, 'number') }} / RUC {{ data_get($guide->external_sender_snapshot, 'issuer_ruc') }}</p>@endif
        @if($guide->status->value === 'uncertain')<div class="alert alert-warning">El resultado del envio es incierto. Concilia con SUNAT antes de intentar cualquier nueva emision.</div>@endif
        <h5>Bienes</h5><div class="table-responsive"><table class="table"><thead><tr><th>#</th><th>Codigo</th><th>Descripcion</th><th>Cantidad</th><th>Unidad</th></tr></thead><tbody>@foreach($guide->items as $item)<tr><td>{{ $item->line_number }}</td><td>{{ $item->code }}</td><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit_code }}</td></tr>@endforeach</tbody></table></div>
        @if($transportAuthorization->hasPermission(auth()->user(), 'transport.guides.export'))
            @if($guide->xml_path)<a href="{{ route('admin.transport.guides.xml', $guide) }}" class="btn btn-outline-primary">Descargar XML</a>@endif
            @if($guide->cdr_path)<a href="{{ route('admin.transport.guides.cdr', $guide) }}" class="btn btn-outline-primary">Descargar CDR</a>@endif
        @endif
    </div></div></div><div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Trazabilidad</h5>@forelse($guide->transmissions as $event)<div class="border-start ps-3 mb-3"><strong>{{ $event->operation }}</strong><div>{{ $event->status_before ?: '-' }} → {{ $event->status_after }}</div><small class="text-muted">{{ $event->occurred_at?->format('d/m/Y H:i:s') }}</small></div>@empty<p class="text-muted">Sin transmisiones.</p>@endforelse</div></div></div></div>
</div>
@endsection
