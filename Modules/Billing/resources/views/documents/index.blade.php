@extends('layouts.admin')

@section('title', 'Documentos electrónicos')

@section('content')
<div class="billing-documents-page py-2">
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

    <div class="card border border-secondary rounded-3 mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="billing-documents-toolbar__title">Operaciones del documento seleccionado</div>
                    <div class="billing-documents-toolbar__meta text-muted" id="selected-document-meta">
                        Selecciona un registro de la tabla para habilitar las acciones.
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form method="POST" action="#" id="selected-redeclare-form" class="d-inline-block mb-0">
                        @csrf
                        <button type="submit" id="selected-redeclare-btn" class="btn btn-warning border rounded-pill px-3" disabled onclick="return confirm('¿Re-declarar este comprobante al proveedor configurado?')">
                            <i class="fas fa-rotate-right mr-1"></i> Re-declarar
                        </button>
                    </form>
                    <a href="#" id="selected-detail-link" class="btn btn-light border rounded-pill px-3 disabled" aria-disabled="true">
                        <i class="fas fa-eye mr-1"></i> Detalle
                    </a>
                    <a href="#" id="selected-history-link" class="btn btn-light border rounded-pill px-3 disabled" aria-disabled="true">
                        <i class="fas fa-clock-rotate-left mr-1"></i> Historial
                    </a>
                    <a href="#" id="selected-xml-link" class="btn btn-light border rounded-pill px-3 disabled" aria-disabled="true">
                        <i class="fas fa-file-code mr-1"></i> XML
                    </a>
                    <a href="#" id="selected-cdr-link" class="btn btn-light border rounded-pill px-3 disabled" aria-disabled="true">
                        <i class="fas fa-file-circle-check mr-1"></i> CDR
                    </a>
                    <a href="#" id="selected-pdf-link" class="btn btn-light border rounded-pill px-3 disabled" aria-disabled="true">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card border border-secondary rounded-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 billing-documents-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 52px;" class="text-center">Sel.</th>
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
                            $issueDateLabel = $document->issue_date?->format('d/m/Y') ?? '-';
                            $totalLabel = number_format((float) $document->total, 2) . ' ' . $document->currency;
                            $documentLabel = $document->series . '-' . $document->number;
                        @endphp
                        <tr class="billing-document-row"
                            tabindex="0"
                            data-row-selectable="true"
                            data-document-label="{{ $documentLabel }}"
                            data-issue-date="{{ $issueDateLabel }}"
                            data-provider="{{ strtoupper((string) $document->provider) }}"
                            data-status="{{ $statusLabel }}"
                            data-total="{{ $totalLabel }}"
                            data-redeclare-url="{{ route('admin.billing.documents.redeclare', $document) }}"
                            data-detail-url="{{ route('admin.billing.documents.show', $document) }}"
                            data-history-url="{{ route('admin.billing.documents.history', $document) }}"
                            data-xml-url="{{ $hasXml ? route('admin.billing.documents.download.xml', $document) : '' }}"
                            data-cdr-url="{{ $hasCdr ? route('admin.billing.documents.download.cdr', $document) : '' }}"
                            data-pdf-url="{{ route('admin.billing.documents.download.pdf', $document) }}">
                            <td class="text-center">
                                <div class="billing-documents-radio">
                                    <i class="far fa-circle"></i>
                                </div>
                            </td>
                            <td>{{ $issueDateLabel }}</td>
                            <td>{{ strtoupper($document->document_type) }}</td>
                            <td>{{ $documentLabel }}</td>
                            <td>
                                @if($document->order_id)
                                    #{{ $document->order_id }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $document->customer_document_number ?? '-' }}</td>
                            <td>{{ strtoupper($document->provider) }}</td>
                            <td class="text-end">{{ $totalLabel }}</td>
                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
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
@endsection

@push('styles')
<style>
    .billing-documents-toolbar__title {
        font-size: 1rem;
        font-weight: 700;
        color: #1f2d3d;
    }

    .billing-documents-toolbar__meta {
        font-size: .9rem;
    }

    .billing-documents-table tbody tr {
        cursor: pointer;
        transition: background-color .15s ease, box-shadow .15s ease;
    }

    .billing-documents-table tbody tr:hover {
        background: rgba(0, 123, 255, .04);
    }

    .billing-documents-table tbody tr.is-selected {
        background: rgba(0, 123, 255, .08);
        box-shadow: inset 3px 0 0 var(--admin-primary-button);
    }

    .billing-documents-radio {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ced4da;
        color: #8c98a5;
        background: #fff;
    }

    .billing-documents-table tbody tr.is-selected .billing-documents-radio {
        border-color: var(--admin-primary-button);
        color: var(--admin-primary-button);
        background: rgba(0, 123, 255, .06);
    }

    .billing-documents-table tbody tr.is-selected .billing-documents-radio i::before {
        content: "\f192";
        font-weight: 900;
    }

    .billing-documents-page .btn.disabled,
    .billing-documents-page .btn[aria-disabled="true"] {
        pointer-events: none;
        opacity: .55;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var rows = document.querySelectorAll('[data-row-selectable="true"]');
        if (!rows.length) {
            return;
        }

        var meta = document.getElementById('selected-document-meta');
        var redeclareForm = document.getElementById('selected-redeclare-form');
        var redeclareButton = document.getElementById('selected-redeclare-btn');
        var detailLink = document.getElementById('selected-detail-link');
        var historyLink = document.getElementById('selected-history-link');
        var xmlLink = document.getElementById('selected-xml-link');
        var cdrLink = document.getElementById('selected-cdr-link');
        var pdfLink = document.getElementById('selected-pdf-link');

        function setLinkState(link, url) {
            if (url) {
                link.href = url;
                link.classList.remove('disabled');
                link.setAttribute('aria-disabled', 'false');
            } else {
                link.href = '#';
                link.classList.add('disabled');
                link.setAttribute('aria-disabled', 'true');
            }
        }

        function selectRow(row) {
            rows.forEach(function (item) {
                item.classList.remove('is-selected');
            });

            row.classList.add('is-selected');

            var label = row.getAttribute('data-document-label') || 'Documento';
            var provider = row.getAttribute('data-provider') || '-';
            var status = row.getAttribute('data-status') || '-';
            var issueDate = row.getAttribute('data-issue-date') || '-';
            var total = row.getAttribute('data-total') || '-';

            meta.textContent = label + ' | ' + provider + ' | ' + status + ' | ' + issueDate + ' | ' + total;

            redeclareForm.action = row.getAttribute('data-redeclare-url') || '#';
            redeclareButton.disabled = false;

            setLinkState(detailLink, row.getAttribute('data-detail-url'));
            setLinkState(historyLink, row.getAttribute('data-history-url'));
            setLinkState(xmlLink, row.getAttribute('data-xml-url'));
            setLinkState(cdrLink, row.getAttribute('data-cdr-url'));
            setLinkState(pdfLink, row.getAttribute('data-pdf-url'));
        }

        rows.forEach(function (row) {
            row.addEventListener('click', function () {
                selectRow(row);
            });

            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectRow(row);
                }
            });
        });

        selectRow(rows[0]);
    })();
</script>
@endpush
