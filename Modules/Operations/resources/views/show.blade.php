@extends('layouts.admin')

@section('title', 'Conciliación #'.$run->id)

@section('content')
<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Conciliación #{{ $run->id }}</h1><p class="text-muted mb-0"><code>{{ $run->correlation_id }}</code></p></div><a class="btn btn-outline-secondary" href="{{ route('admin.operations.index') }}">Volver</a></div>
    <div class="card mb-4"><div class="card-body"><div class="row g-3"><div class="col-md-2"><small class="text-muted d-block">Estado</small>{{ $run->status }}</div><div class="col-md-2"><small class="text-muted d-block">Duración</small>{{ $run->duration_ms ?? '—' }} ms</div><div class="col-md-2"><small class="text-muted d-block">Saldos</small>{{ $run->checked_inventory_balances }}</div><div class="col-md-2"><small class="text-muted d-block">Documentos</small>{{ $run->checked_inventory_documents }}</div><div class="col-md-2"><small class="text-muted d-block">Eventos</small>{{ $run->checked_economic_events }}</div><div class="col-md-2"><small class="text-muted d-block">Asientos</small>{{ $run->checked_accounting_entries }}</div></div></div></div>
    <div class="card"><div class="card-header fw-semibold">Hallazgos inmutables</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Severidad</th><th>Dominio</th><th>Código</th><th>Fuente</th><th>Esperado</th><th>Actual</th></tr></thead><tbody>
        @forelse($run->issues as $issue)<tr><td><span class="badge text-bg-{{ $issue->severity === 'critical' ? 'danger' : 'warning' }}">{{ $issue->severity }}</span></td><td>{{ $issue->domain }}</td><td><code>{{ $issue->issue_code }}</code></td><td>{{ class_basename((string) $issue->source_type) }} {{ $issue->source_id }}</td><td><pre class="small mb-0">{{ json_encode($issue->expected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre></td><td><pre class="small mb-0">{{ json_encode($issue->actual, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre></td></tr>@empty<tr><td colspan="6" class="text-center py-4">No se detectaron desviaciones.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
