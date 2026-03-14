@php
    $selectedHasXml = $selectedDocument && (bool) ($selectedDocument->xmlFile() || $selectedDocument->xml_path || data_get($selectedDocument->request_payload, 'xml_path'));
    $selectedHasCdr = $selectedDocument && (bool) ($selectedDocument->cdrFile() || data_get($selectedDocument->response_payload, 'cdr_path') || data_get($selectedDocument->response_payload, 'cdr_base64') || data_get($selectedDocument->response_payload, 'body.cdr_base64') || data_get($selectedDocument->response_payload, 'body.cdrZipBase64'));
@endphp

<div class="space-y-6">
    <x-admin.page-header
        title="Documentos electronicos"
        description="Monitorea comprobantes emitidos, filtra por proveedor y entra al detalle operativo de cada envio."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.billing.settings.edit') }}" variant="outline" icon="cog-6-tooth">
                Configuracion
            </flux:button>
            <flux:button href="{{ route('admin.electronic-documents.templates.index') }}" variant="outline" icon="document-text">
                Plantillas PDF
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 md:grid-cols-[1.2fr,1fr,1fr,1fr,1.4fr]">
                <div>
                    <label class="form-label">Proveedor</label>
                    <select wire:model.live="provider" class="form-select">
                        <option value="">Todos</option>
                        @foreach($providers as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select wire:model.live="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach($statuses as $item)
                            <option value="{{ $item }}">{{ strtoupper($item) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Desde</label>
                    <input wire:model.live="dateFrom" type="date" class="form-control">
                </div>
                <div>
                    <label class="form-label">Hasta</label>
                    <input wire:model.live="dateTo" type="date" class="form-control">
                </div>
                <div>
                    <label class="form-label">Buscar</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Serie, numero, DNI/RUC u orden" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">
                    Limpiar filtros
                </flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $documents->total() }} comprobantes encontrados
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Documento seleccionado</div>
                    <div class="mt-1 text-sm text-slate-500">
                        @if($selectedDocument)
                            {{ $selectedDocument->series }}-{{ $selectedDocument->number }}
                            | {{ strtoupper((string) $selectedDocument->provider) }}
                            | {{ strtoupper(str_replace('_', ' ', (string) $selectedDocument->status)) }}
                            | {{ $selectedDocument->issue_date?->format('d/m/Y') ?? '-' }}
                            | {{ number_format((float) $selectedDocument->total, 2) }} {{ $selectedDocument->currency }}
                        @else
                            Selecciona un comprobante para habilitar acciones.
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($selectedDocument)
                        <form method="POST" action="{{ route('admin.billing.documents.redeclare', $selectedDocument) }}" class="m-0">
                            @csrf
                            <flux:button type="submit" variant="filled" color="amber" icon="arrow-path" onclick="return confirm('¿Re-declarar este comprobante al proveedor configurado?')">
                                Re-declarar
                            </flux:button>
                        </form>
                        <flux:button href="{{ route('admin.billing.documents.show', $selectedDocument) }}" variant="outline" icon="eye">
                            Detalle
                        </flux:button>
                        <flux:button href="{{ route('admin.billing.documents.history', $selectedDocument) }}" variant="outline" icon="clock">
                            Historial
                        </flux:button>
                        <flux:button href="{{ $selectedHasXml ? route('admin.billing.documents.download.xml', $selectedDocument) : '#' }}" variant="outline" icon="code-bracket-square" @disabled(! $selectedHasXml)>
                            XML
                        </flux:button>
                        <flux:button href="{{ $selectedHasCdr ? route('admin.billing.documents.download.cdr', $selectedDocument) : '#' }}" variant="outline" icon="shield-check" @disabled(! $selectedHasCdr)>
                            CDR
                        </flux:button>
                        <flux:button href="{{ route('admin.billing.documents.download.pdf', $selectedDocument) }}" variant="primary" icon="document-arrow-down">
                            PDF
                        </flux:button>
                    @else
                        <flux:button variant="outline" disabled icon="eye">Detalle</flux:button>
                        <flux:button variant="outline" disabled icon="clock">Historial</flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div wire:loading.flex class="align-items-center justify-content-center border-bottom px-4 py-3 text-sm text-muted">
                Actualizando comprobantes...
            </div>

            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th>Pedido</th>
                            <th>Cliente Doc.</th>
                            <th>Proveedor</th>
                            <th class="text-end">Total</th>
                            <th>Estado</th>
                            <th class="text-end">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            @php
                                $statusValue = strtolower((string) $document->status);
                                $badgeClass = match ($statusValue) {
                                    'issued', 'accepted' => 'badge badge-success',
                                    'error', 'rejected', 'accepted_with_observation', 'accepted-observation', 'accepted_observation' => 'badge badge-danger',
                                    'queued' => 'badge badge-warning',
                                    default => 'badge badge-secondary',
                                };
                                $isSelected = $selectedDocument?->id === $document->id;
                            @endphp
                            <tr wire:key="billing-document-{{ $document->id }}" @class(['bg-slate-50' => $isSelected])>
                                <td>{{ $document->issue_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ strtoupper((string) $document->document_type) }}</td>
                                <td class="fw-semibold">{{ $document->series }}-{{ $document->number }}</td>
                                <td>{{ $document->order_id ? '#'.$document->order_id : '-' }}</td>
                                <td>{{ $document->customer_document_number ?? '-' }}</td>
                                <td>{{ strtoupper((string) $document->provider) }}</td>
                                <td class="text-end">{{ number_format((float) $document->total, 2) }} {{ $document->currency }}</td>
                                <td><span class="{{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', (string) $document->status)) }}</span></td>
                                <td class="text-end">
                                    <flux:button wire:click="selectDocument({{ $document->id }})" variant="{{ $isSelected ? 'primary' : 'outline' }}" size="sm">
                                        {{ $isSelected ? 'Activo' : 'Seleccionar' }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-5 text-center text-muted">No hay documentos electronicos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $documents->links() }}
    </div>
</div>
