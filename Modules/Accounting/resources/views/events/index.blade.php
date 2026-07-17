@extends('layouts.admin')

@section('title', 'Eventos económicos')

@section('content')
<div class="container-fluid py-2">
    <x-admin.page-header title="Eventos económicos" />
    <div class="card border border-secondary rounded-3 mb-4"><div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4"><input name="search" value="{{ $search }}" class="form-control" placeholder="Fuente o clave idempotente"></div>
            <div class="col-md-3"><select name="type" class="form-select"><option value="">Todos los tipos</option>@foreach($types as $option)<option value="{{ $option->value }}" @selected($type === $option->value)>{{ $option->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select"><option value="">Todos los estados</option>@foreach($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
        </form>
    </div></div>
    <div class="card border border-secondary rounded-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>ID</th><th>Fecha</th><th>Tipo</th><th>Fuente</th><th>Estado</th><th>Intentos</th><th>Asiento</th><th></th></tr></thead>
        <tbody>@forelse($events as $event)<tr>
            <td>{{ $event->id }}</td><td>{{ $event->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $event->event_type->label() }}</td><td>{{ $event->source_code ?: $event->source_id }}</td>
            <td><span class="badge text-bg-{{ $event->status->value === 'processed' ? 'success' : ($event->status->value === 'error' ? 'danger' : 'secondary') }}">{{ $event->status->label() }}</span></td>
            <td>{{ $event->attempts }}</td><td>{{ $event->entry?->reference ?? '—' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.accounting.events.show', $event) }}">Detalle</a></td>
        </tr>@empty<tr><td colspan="8" class="text-center py-4">No hay eventos económicos.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer">{{ $events->links() }}</div></div>
</div>
@endsection
