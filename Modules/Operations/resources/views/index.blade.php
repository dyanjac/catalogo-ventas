@extends('layouts.admin')

@section('title', 'Operaciones y observabilidad')

@section('content')
<div class="container-fluid py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h1 class="h3 mb-1">Operaciones y observabilidad</h1><p class="text-muted mb-0">Conciliación integral, incidentes y métricas por organización.</p></div>
        @if(app(Modules\Security\Services\SecurityAuthorizationService::class)->hasPermission(auth()->user(), 'operations.reconciliations.run'))
            <form method="POST" action="{{ route('admin.operations.runs.store') }}">@csrf<button class="btn btn-primary">Ejecutar conciliación</button></form>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Último estado</div><div class="fs-4 fw-semibold">{{ $latestRun?->status ?? 'Sin ejecución' }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Hallazgos</div><div class="fs-4 fw-semibold">{{ $latestRun?->issue_count ?? 0 }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Críticos</div><div class="fs-4 fw-semibold text-danger">{{ $latestRun?->critical_count ?? 0 }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Duración</div><div class="fs-4 fw-semibold">{{ $latestRun?->duration_ms ? number_format($latestRun->duration_ms).' ms' : '—' }}</div></div></div></div>
    </div>

    <div class="card mb-4"><div class="card-header fw-semibold">Incidentes activos</div><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Severidad</th><th>Dominio</th><th>Código</th><th>Estado</th><th>Ocurrencias</th><th>Última detección</th><th></th></tr></thead>
        <tbody>@forelse($incidents as $incident)<tr><td><span class="badge text-bg-{{ $incident->severity === 'critical' ? 'danger' : 'warning' }}">{{ $incident->severity }}</span></td><td>{{ $incident->domain }}</td><td><code>{{ $incident->issue_code }}</code></td><td>{{ $incident->status }}</td><td>{{ $incident->occurrences }}</td><td>{{ $incident->last_seen_at?->format('d/m/Y H:i') }}</td><td>
            @if($incident->status === 'open' && app(Modules\Security\Services\SecurityAuthorizationService::class)->hasPermission(auth()->user(), 'operations.incidents.manage'))
            <form method="POST" action="{{ route('admin.operations.incidents.acknowledge', $incident) }}" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" name="note" maxlength="500" placeholder="Nota opcional"><button class="btn btn-sm btn-outline-primary">Reconocer</button></form>
            @endif
        </td></tr>@empty<tr><td colspan="7" class="text-center py-4">No hay incidentes activos.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer">{{ $incidents->links() }}</div></div>

    <div class="card"><div class="card-header fw-semibold">Historial de conciliaciones</div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>ID</th><th>Inicio</th><th>Disparador</th><th>Estado</th><th>Revisados</th><th>Hallazgos</th><th></th></tr></thead>
        <tbody>@forelse($runs as $run)<tr><td>{{ $run->id }}</td><td>{{ $run->started_at?->format('d/m/Y H:i') }}</td><td>{{ $run->trigger }}</td><td>{{ $run->status }}</td><td>{{ $run->checked_inventory_balances + $run->checked_inventory_documents + $run->checked_economic_events + $run->checked_accounting_entries }}</td><td>{{ $run->issue_count }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.operations.runs.show', $run) }}">Detalle</a></td></tr>@empty<tr><td colspan="7" class="text-center py-4">No hay ejecuciones registradas.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer">{{ $runs->links() }}</div></div>
</div>
@endsection
