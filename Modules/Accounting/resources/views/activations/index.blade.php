@extends('layouts.admin')

@section('title', 'Activación contable histórica')

@section('content')
<div class="container-fluid py-2">
    <x-admin.page-header title="Activación contable histórica" />

    <div class="alert alert-warning border-warning">
        La fecha de corte es inclusiva. La simulación no crea eventos ni asientos y solo podrá confirmarse si no existen inconsistencias.
        Los saldos de apertura anteriores al corte no se infieren en esta fase.
    </div>

    <div class="card border border-secondary rounded-3 mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.accounting.activations.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" for="cutoff_date">Fecha de corte (UTC)</label>
                    <input id="cutoff_date" name="cutoff_date" type="date" value="{{ old('cutoff_date') }}" class="form-control @error('cutoff_date') is-invalid @enderror" required>
                    @error('cutoff_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4"><button class="btn btn-primary">Ejecutar simulación sellada</button></div>
            </form>
        </div>
    </div>

    <div class="card border border-secondary rounded-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Run</th><th>Ventana</th><th>Estado</th><th>Elegibles</th><th>Existentes</th><th>Errores</th><th>Procesados</th><th></th></tr></thead>
            <tbody>@forelse($runs as $run)<tr>
                <td>#{{ $run->id }}</td>
                <td>{{ $run->cutoff_at?->format('d/m/Y') }} — {{ $run->captured_through_at?->format('d/m/Y H:i') }}</td>
                <td><span class="badge text-bg-{{ $run->status === 'completed' ? 'success' : (in_array($run->status, ['blocked','failed']) ? 'danger' : 'secondary') }}">{{ $run->status }}</span></td>
                <td>{{ $run->eligible_count }}</td><td>{{ $run->existing_count }}</td><td>{{ $run->error_count }}</td><td>{{ $run->processed_count }}</td>
                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.accounting.activations.show', $run) }}">Detalle</a></td>
            </tr>@empty<tr><td colspan="8" class="text-center py-4">Todavía no existen simulaciones históricas.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="card-footer">{{ $runs->links() }}</div>
    </div>
</div>
@endsection
