<div class="space-y-6">
    <x-admin.page-header
        title="Auditoria de seguridad"
        description="Consulta cambios de accesos, autenticacion, pruebas LDAP y eventos relevantes del modulo Security."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.security.roles.index') }}" variant="outline" icon="shield-check">
                Roles y permisos
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 md:grid-cols-[1fr,1fr,2fr]">
                <div>
                    <label class="form-label">Tipo de evento</label>
                    <select wire:model.live="eventType" class="form-select">
                        <option value="">Todos</option>
                        @foreach($eventTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Resultado</label>
                    <select wire:model.live="result" class="form-select">
                        <option value="">Todos</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Buscar</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Codigo, mensaje o modulo" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">Limpiar filtros</flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $logs->total() }} eventos registrados
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Actor</th>
                            <th>Objetivo</th>
                            <th>Resultado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr wire:key="audit-log-{{ $log->id }}">
                                <td>
                                    <div class="fw-semibold text-slate-900">{{ $log->event_code }}</div>
                                    <small class="text-muted">{{ $log->message ?: ($log->module ?: 'security') }}</small>
                                </td>
                                <td>
                                    <div>{{ $log->actor?->name ?: 'Sistema' }}</div>
                                    <small class="text-muted">{{ $log->actor?->email }}</small>
                                </td>
                                <td>
                                    <div>{{ $log->target?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $log->target?->email }}</small>
                                </td>
                                <td>
                                    <span class="badge {{ $log->result === 'success' ? 'bg-success' : ($log->result === 'warning' ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ strtoupper($log->result) }}
                                    </span>
                                </td>
                                <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">No hay eventos de auditoria registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $logs->links() }}
    </div>
</div>
