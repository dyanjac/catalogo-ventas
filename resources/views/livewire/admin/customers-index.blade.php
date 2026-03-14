<div class="space-y-6">
    <x-admin.page-header
        title="Clientes registrados"
        description="Gestiona la base de clientes, perfiles de acceso y estado operativo de cada usuario."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.dashboard') }}" variant="outline" icon="home">
                Dashboard
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 md:grid-cols-[2fr,1fr]">
                <div>
                    <label class="form-label">Buscar cliente</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Nombre, correo, celular o documento" />
                </div>
                <div>
                    <label class="form-label">Perfil</label>
                    <select wire:model.live="role" class="form-select">
                        <option value="">Todos</option>
                        <option value="customer">Cliente</option>
                        <option value="super_admin">Super usuario</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">
                    Limpiar filtros
                </flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $customers->total() }} usuarios encontrados
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div wire:loading.flex class="align-items-center justify-content-center border-bottom px-4 py-3 text-sm text-muted">
                Actualizando clientes...
            </div>

            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Perfil</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr wire:key="customer-{{ $customer->id }}">
                                <td>
                                    <div class="fw-semibold text-slate-800">{{ $customer->name }}</div>
                                    <small class="text-muted">{{ $customer->email }}</small>
                                </td>
                                <td>
                                    <div>{{ $customer->phone ?: '-' }}</div>
                                    <small class="text-muted">{{ $customer->document_number ?: 'Sin documento' }}</small>
                                </td>
                                <td>
                                    <span class="badge {{ $customer->role === 'super_admin' ? 'bg-dark' : 'bg-primary' }}">
                                        {{ $customer->role === 'super_admin' ? 'Super usuario' : 'Cliente' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $customer->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $customer->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <flux:button href="{{ route('admin.customers.show', $customer) }}" variant="primary" size="sm">
                                        Gestionar
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">No hay usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $customers->links() }}
    </div>
</div>
