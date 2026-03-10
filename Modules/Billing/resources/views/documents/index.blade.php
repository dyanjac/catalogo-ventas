@extends('layouts.admin')

@section('title', 'Documentos electrónicos')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Documentos electrónicos emitidos">
            <x-slot:actions>
                <a href="{{ route('admin.billing.settings.edit') }}" class="btn btn-light border rounded-pill px-4">Configuración</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.billing.documents.index') }}" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Proveedor</label>
                        <select name="provider" class="form-select">
                            <option value="">Todos</option>
                            @foreach($providers as $code => $label)
                                <option value="{{ $code }}" @selected($provider === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach($statuses as $item)
                                <option value="{{ $item }}" @selected($status === $item)>{{ strtoupper($item) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Serie, número, DNI/RUC u orden">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Filtrar</button>
                        <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
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
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th>Pedido</th>
                            <th>Cliente Doc.</th>
                            <th>Proveedor</th>
                            <th class="text-end">Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            <tr>
                                <td>{{ $document->issue_date?->format('d/m/Y') }}</td>
                                <td>{{ strtoupper($document->document_type) }}</td>
                                <td>{{ $document->series }}-{{ $document->number }}</td>
                                <td>
                                    @if($document->order_id)
                                        #{{ $document->order_id }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $document->customer_document_number ?? '-' }}</td>
                                <td>{{ strtoupper($document->provider) }}</td>
                                <td class="text-end">{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($document->status) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay documentos electrónicos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $documents->links() }}</div>
        </div>
    </div>
</div>
@endsection
