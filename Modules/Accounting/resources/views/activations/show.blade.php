@extends('layouts.admin')

@section('title', 'Activación histórica #'.$run->id)

@section('content')
<div class="container-fluid py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h3 mb-1">Activación histórica #{{ $run->id }}</h1><div class="text-muted">Corte inclusivo {{ $run->cutoff_at?->format('d/m/Y H:i') }} · captura {{ $run->captured_through_at?->format('d/m/Y H:i') }} UTC</div></div>
        <a href="{{ route('admin.accounting.activations.index') }}" class="btn btn-light border">Volver</a>
    </div>

    @if($run->error_message)<div class="alert alert-danger"><strong>{{ $run->error_code }}</strong> · {{ $run->error_message }}</div>@endif
    @if(data_get($run->summary, '_run_issues'))
        <div class="alert alert-danger"><strong>Configuración bloqueante</strong>@foreach(data_get($run->summary, '_run_issues', []) as $issue)<div><code>{{ $issue['code'] }}</code> · {{ $issue['message'] }}</div>@endforeach</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach(['Estado' => $run->status, 'Elegibles' => $run->eligible_count, 'Ya contabilizados' => $run->existing_count, 'Inconsistencias' => $run->error_count, 'Procesados' => $run->processed_count] as $label => $value)
            <div class="col"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="card border border-secondary rounded-3 mb-4">
        <div class="card-body">
            <div class="small text-muted mb-1">Hash aprobado</div><code class="text-break">{{ $run->simulation_hash }}</code>
            @if($run->status === 'simulated')
                <hr>
                <form method="POST" action="{{ route('admin.accounting.activations.confirm', $run) }}" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="simulation_hash" value="{{ $run->simulation_hash }}">
                    <div class="col-lg-7">
                        <label class="form-label">Escriba <code>CONFIRMAR {{ $run->confirmation_token }}</code></label>
                        <input name="confirmation" class="form-control @error('confirmation') is-invalid @enderror" autocomplete="off" required>
                        @error('confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-5"><button class="btn btn-danger">Confirmar y publicar snapshot</button></div>
                </form>
            @elseif(in_array($run->status, ['failed','confirmed']))
                <hr><form method="POST" action="{{ route('admin.accounting.activations.reprocess', $run) }}">@csrf<button class="btn btn-warning">Reprocesar idempotentemente</button></form>
            @elseif($run->status === 'blocked')
                <hr><div class="text-danger">Corrija las fuentes o la configuración y genere una nueva simulación. Esta evidencia queda conservada.</div>
            @endif
        </div>
    </div>

    <div class="card border border-secondary rounded-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Fuente</th><th>Estado</th><th>Dependencia</th><th>Resultado / incidencias</th></tr></thead>
        <tbody>@forelse($run->items as $item)<tr>
            <td>{{ $item->occurred_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td><td>{{ $item->event_type }}</td><td>{{ $item->source_code ?: $item->source_id }}</td>
            <td><span class="badge text-bg-{{ $item->status === 'processed' || $item->status === 'already_present' ? 'success' : ($item->status === 'inconsistent' ? 'danger' : 'secondary') }}">{{ $item->status }}</span></td>
            <td><code>{{ $item->dependency_key ?: '—' }}</code></td>
            <td>@if($item->issues)@foreach($item->issues as $issue)<div><code>{{ $issue['code'] }}</code> · {{ $issue['message'] }}</div>@endforeach@else Débito {{ number_format((float) data_get($item->configuration_snapshot, 'total_debit', 0), 2) }} / Crédito {{ number_format((float) data_get($item->configuration_snapshot, 'total_credit', 0), 2) }} @endif</td>
        </tr>@empty<tr><td colspan="6" class="text-center py-4">No se encontraron hechos en la ventana seleccionada.</td></tr>@endforelse</tbody>
    </table></div></div>
</div>
@endsection
