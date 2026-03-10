@extends('layouts.admin')

@section('title', 'Documentos electrónicos')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Documentos electrónicos emitidos">
            <x-slot:actions>
                <a href="{{ route('admin.billing.settings.edit') }}" class="btn btn-light border rounded-pill px-4">Configuración</a>
                <a href="{{ route('admin.electronic-documents.templates.index') }}" class="btn btn-light border rounded-pill px-4">Plantillas PDF</a>
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
                            <th class="text-end">Descargas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            <tr>
                                @php
                                    $hasXml = (bool) ($document->xmlFile() || $document->xml_path || data_get($document->request_payload, 'xml_path'));
                                    $hasCdr = (bool) ($document->cdrFile() || data_get($document->response_payload, 'cdr_path') || data_get($document->response_payload, 'cdr_base64') || data_get($document->response_payload, 'body.cdr_base64') || data_get($document->response_payload, 'body.cdrZipBase64'));
                                    $statusValue = strtolower((string) $document->status);
                                    $statusClass = match ($statusValue) {
                                        'issued' => 'bg-success',
                                        'error', 'rejected', 'accepted_with_observation', 'accepted-observation', 'accepted_observation' => 'bg-danger',
                                        'accepted' => 'bg-success',
                                        'queued' => 'bg-warning text-dark',
                                        default => 'bg-secondary',
                                    };
                                    $statusLabel = str_replace('_', ' ', strtoupper((string) $document->status));
                                @endphp
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
                                <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.billing.documents.redeclare', $document) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning border" title="Re-declarar al proveedor" onclick="return confirm('¿Re-declarar este comprobante al proveedor configurado?')">
                                            <i class="fas fa-rotate-right"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.billing.documents.show', $document) }}" class="btn btn-sm btn-light border" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ $hasXml ? route('admin.billing.documents.download.xml', $document) : '#' }}" class="btn btn-sm btn-light border {{ $hasXml ? '' : 'disabled' }}" title="Descargar XML">
                                        <i class="fas fa-file-code"></i>
                                    </a>
                                    <a href="{{ $hasCdr ? route('admin.billing.documents.download.cdr', $document) : '#' }}" class="btn btn-sm btn-light border {{ $hasCdr ? '' : 'disabled' }}" title="Descargar CDR">
                                        <i class="fas fa-file-circle-check"></i>
                                    </a>
                                    <a href="{{ route('admin.billing.documents.download.pdf', $document) }}" class="btn btn-sm btn-light border" title="Descargar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No hay documentos electrónicos registrados.</td>
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
