@extends('layouts.admin')

@section('title', 'Detalle de comprobante electrónico')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="'Comprobante ' . $document->series . '-' . $document->number">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.billing.documents.redeclare', $document) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning border rounded-pill px-4" onclick="return confirm('¿Re-declarar este comprobante al proveedor configurado?')">
                        Re-declarar
                    </button>
                </form>
                <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border border-secondary rounded-3">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Resumen</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-5">Tipo</dt>
                            <dd class="col-7">{{ strtoupper($document->document_type) }}</dd>
                            <dt class="col-5">Proveedor</dt>
                            <dd class="col-7">{{ strtoupper($document->provider) }}</dd>
                            <dt class="col-5">Estado</dt>
                            <dd class="col-7"><span class="badge bg-secondary">{{ strtoupper($document->status) }}</span></dd>
                            <dt class="col-5">Fecha emisión</dt>
                            <dd class="col-7">{{ $document->issue_date?->format('d/m/Y') }}</dd>
                            <dt class="col-5">Cliente Doc.</dt>
                            <dd class="col-7">{{ $document->customer_document_type }} {{ $document->customer_document_number }}</dd>
                            <dt class="col-5">Total</dt>
                            <dd class="col-7">{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</dd>
                            <dt class="col-5">Pedido</dt>
                            <dd class="col-7">{{ $document->order_id ? '#'.$document->order_id : '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card border border-secondary rounded-3">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Historial de respuestas del proveedor</h3>
                    </div>
                    <div class="card-body">
                        @forelse($document->responseHistories as $history)
                            @php
                                $dispatchMode = (string) data_get($history->request_payload, '_dispatch.mode', data_get($history->response_payload, 'dispatch.mode', 'sync'));
                                $dispatchConnection = data_get($history->request_payload, '_dispatch.connection', data_get($history->response_payload, 'dispatch.connection'));
                                $dispatchQueue = data_get($history->request_payload, '_dispatch.queue', data_get($history->response_payload, 'dispatch.queue'));
                                $eventLabel = str_replace('_', ' ', strtoupper((string) $history->event));
                            @endphp
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong>{{ strtoupper($history->provider ?? '-') }}</strong>
                                        <span class="text-muted"> · {{ strtoupper($history->environment ?? '-') }}</span>
                                        <span class="badge bg-light text-dark border ms-2">{{ $eventLabel }}</span>
                                        @if($dispatchMode === 'queue')
                                            <span class="badge bg-info text-dark ms-1">COLA</span>
                                        @else
                                            <span class="badge bg-primary ms-1">EN LÍNEA</span>
                                        @endif
                                    </div>
                                    <div>
                                        @if($history->ok)
                                            <span class="badge bg-success">OK</span>
                                        @else
                                            <span class="badge bg-danger">ERROR</span>
                                        @endif
                                        <span class="text-muted ms-2">{{ $history->created_at?->format('d/m/Y H:i:s') }}</span>
                                    </div>
                                </div>
                                <div class="mb-2"><strong>Mensaje:</strong> {{ $history->message ?: '-' }}</div>
                                @if($dispatchMode === 'queue')
                                    <div class="mb-2">
                                        <strong>Canal:</strong>
                                        cola{{ $dispatchConnection ? ' · '.$dispatchConnection : '' }}{{ $dispatchQueue ? ' / '.$dispatchQueue : '' }}
                                    </div>
                                @else
                                    <div class="mb-2"><strong>Canal:</strong> en línea</div>
                                @endif
                                @if($history->status_code)
                                    <div class="mb-2"><strong>Status HTTP:</strong> {{ $history->status_code }}</div>
                                @endif
                                @if($history->error_class || $history->error_message)
                                    <div class="mb-2 text-danger"><strong>Excepción:</strong> {{ $history->error_class }} {{ $history->error_message }}</div>
                                @endif
                                <details>
                                    <summary>Ver payload request/response</summary>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Request</label>
                                            <pre class="small border rounded p-2 bg-light">{{ json_encode($history->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Response</label>
                                            <pre class="small border rounded p-2 bg-light">{{ json_encode($history->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No hay historial de respuestas para este comprobante.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
