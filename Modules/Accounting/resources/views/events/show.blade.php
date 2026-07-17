@extends('layouts.admin')

@section('title', 'Evento económico #'.$event->id)

@section('content')
<div class="container-fluid py-2">
    <x-admin.page-header title="Evento económico #{{ $event->id }}" />
    <div class="card border border-secondary rounded-3 mb-4"><div class="card-body row g-3">
        <div class="col-md-3"><strong>Tipo</strong><div>{{ $event->event_type->label() }}</div></div>
        <div class="col-md-3"><strong>Estado</strong><div>{{ $event->status->label() }}</div></div>
        <div class="col-md-3"><strong>Fuente</strong><div>{{ $event->source_code ?: $event->source_id }}</div></div>
        <div class="col-md-3"><strong>Intentos</strong><div>{{ $event->attempts }}</div></div>
        <div class="col-12"><strong>Clave idempotente</strong><div class="font-monospace">{{ $event->idempotency_key }}</div></div>
        @if($event->error_message)<div class="col-12"><div class="alert alert-danger mb-0"><strong>{{ $event->error_code }}</strong><br>{{ $event->error_message }}</div></div>@endif
    </div></div>
    @if($event->entry)<div class="card border border-secondary rounded-3 mb-4"><div class="card-header">Asiento {{ $event->entry->reference }}</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Cuenta</th><th>Detalle</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead><tbody>@foreach($event->entry->lines as $line)<tr><td>{{ $line->account_code }} · {{ $line->account_name }}</td><td>{{ $line->line_description }}</td><td class="text-end">{{ number_format((float)$line->debit, 2) }}</td><td class="text-end">{{ number_format((float)$line->credit, 2) }}</td></tr>@endforeach</tbody></table></div></div>@endif
    <div class="d-flex gap-2">
        <a href="{{ route('admin.accounting.events.index') }}" class="btn btn-light border">Volver</a>
        @if(in_array($event->status->value, ['pending','error']))<form method="POST" action="{{ route('admin.accounting.events.process', $event) }}">@csrf<button class="btn btn-primary">Procesar / reintentar</button></form>@endif
        @if($event->status->value === 'processed')<form method="POST" action="{{ route('admin.accounting.events.reverse', $event) }}" class="d-flex gap-2">@csrf<input type="text" name="idempotency_key" class="form-control" value="event:{{ $event->id }}:reversal:1" required><button class="btn btn-outline-danger">Crear reversión</button></form>@endif
    </div>
</div>
@endsection
