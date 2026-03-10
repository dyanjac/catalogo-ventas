@extends('layouts.admin')

@section('title', 'Periodos contables')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Periodos contables" />

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.accounting.periods.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label">Año</label>
                        <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ old('year', now()->year) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Mes</label>
                        <input type="number" min="1" max="12" name="month" class="form-control" value="{{ old('month', now()->month) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Inicio</label>
                        <input type="date" name="starts_at" class="form-control" value="{{ old('starts_at') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fin</label>
                        <input type="date" name="ends_at" class="form-control" value="{{ old('ends_at') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select" required>
                            <option value="open" @selected(old('status') === 'open')>Abierto</option>
                            <option value="closed" @selected(old('status') === 'closed')>Cerrado</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary rounded-pill px-4">Crear periodo</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-secondary rounded-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Año</th>
                            <th>Mes</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado</th>
                            <th>Cierre</th>
                            <th class="text-end">Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                            <tr>
                                <form method="POST" action="{{ route('admin.accounting.periods.update', $period) }}">
                                    @csrf
                                    @method('PUT')
                                    <td><input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $period->year }}" required></td>
                                    <td><input type="number" min="1" max="12" name="month" class="form-control" value="{{ $period->month }}" required></td>
                                    <td><input type="date" name="starts_at" class="form-control" value="{{ optional($period->starts_at)->toDateString() }}" required></td>
                                    <td><input type="date" name="ends_at" class="form-control" value="{{ optional($period->ends_at)->toDateString() }}" required></td>
                                    <td>
                                        <select name="status" class="form-select" required>
                                            <option value="open" @selected($period->status === 'open')>Abierto</option>
                                            <option value="closed" @selected($period->status === 'closed')>Cerrado</option>
                                        </select>
                                    </td>
                                    <td class="small text-muted">
                                        @if($period->closed_at)
                                            {{ $period->closed_at->format('Y-m-d H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary rounded-pill px-3">Guardar</button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No hay periodos contables registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $periods->links() }}</div>
        </div>
    </div>
</div>
@endsection
