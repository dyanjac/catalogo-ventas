@extends('layouts.admin')

@section('title', 'Asientos contables')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Asientos contables por periodo">
            <x-slot:actions>
                <a href="{{ route('admin.accounting.settings.edit') }}" class="btn btn-light border rounded-pill px-4">Configuración</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.accounting.entries.index') }}" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Año</label>
                        <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $year }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Mes</label>
                        <input type="number" min="1" max="12" name="month" class="form-control" value="{{ $month }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach($statuses as $entryStatus)
                                <option value="{{ $entryStatus }}" @selected($status === $entryStatus)>{{ strtoupper($entryStatus) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Referencia, glosa, cuenta...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Filtrar</button>
                        <a href="{{ route('admin.accounting.entries.index') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-secondary rounded-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Periodo</th>
                            <th>Comprobante</th>
                            <th>Referencia</th>
                            <th>Estado</th>
                            <th class="text-end">Débito</th>
                            <th class="text-end">Crédito</th>
                            <th class="text-center">Líneas</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date?->format('d/m/Y') }}</td>
                                <td>{{ $entry->period_year }}-{{ str_pad((string) $entry->period_month, 2, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $entry->voucher_type ?? '-' }} {{ trim(($entry->voucher_series ?? '') . ' ' . ($entry->voucher_number ?? '')) }}</td>
                                <td>{{ $entry->reference ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($entry->status) }}</span></td>
                                <td class="text-end">{{ number_format((float) $entry->total_debit, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $entry->total_credit, 2) }}</td>
                                <td class="text-center">{{ $entry->lines_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.accounting.entries.edit', $entry) }}" class="btn btn-sm btn-primary rounded-pill px-3">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No hay asientos para el periodo seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
