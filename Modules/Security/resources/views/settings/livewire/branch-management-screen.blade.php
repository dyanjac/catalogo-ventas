<div class="space-y-6">
    <x-admin.page-header
        title="Sucursales"
        description="Define la estructura organizacional base del panel. El alcance branch del RBAC filtra usuarios, pedidos y comprobantes por estas sucursales."
    >
        <x-slot:actions>
            <flux:button type="button" wire:click="createBranch" variant="primary" icon="plus">
                Nueva sucursal
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.05fr,1fr]">
        <div class="space-y-4">
            <div class="card border-0">
                <div class="card-body">
                    <label class="form-label">Buscar sucursal</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Codigo, nombre o ciudad" />
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Ciudad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($branches as $branch)
                                    <tr wire:key="branch-{{ $branch->id }}">
                                        <td>
                                            <div class="fw-semibold text-slate-900">{{ $branch->name }}</div>
                                            <small class="text-muted">{{ $branch->code }}</small>
                                            @if($branch->is_default)
                                                <span class="badge bg-info ms-2">Default</span>
                                            @endif
                                        </td>
                                        <td>{{ $branch->city ?: 'Sin ciudad' }}</td>
                                        <td>
                                            <span class="badge {{ $branch->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $branch->is_active ? 'Activa' : 'Inactiva' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <flux:button type="button" wire:click="selectBranch({{ $branch->id }})" variant="{{ $selectedBranchId === $branch->id ? 'primary' : 'outline' }}" size="sm">
                                                Gestionar
                                            </flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-5 text-center text-muted">No hay sucursales registradas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                {{ $branches->links() }}
            </div>
        </div>

        <div>
            <form wire:submit="save" class="space-y-4">
                <div class="card border-0">
                    <div class="card-body space-y-4">
                        <div>
                            <div class="text-uppercase text-xs tracking-[0.3em] text-primary">Configuracion organizacional</div>
                            <h3 class="mb-1 text-2xl font-semibold text-slate-900">{{ $selectedBranchId ? 'Editar sucursal' : 'Nueva sucursal' }}</h3>
                            <p class="mb-0 text-muted">Cada usuario administrativo puede pertenecer a una sucursal base para el alcance branch.</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="form-label">Codigo</label>
                                <input type="text" wire:model="code" class="form-control" placeholder="MAIN" />
                                @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Nombre</label>
                                <input type="text" wire:model="name" class="form-control" placeholder="Sucursal principal" />
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Ciudad</label>
                                <input type="text" wire:model="city" class="form-control" placeholder="Lima" />
                                @error('city') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Telefono</label>
                                <input type="text" wire:model="phone" class="form-control" placeholder="999999999" />
                                @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Direccion</label>
                            <textarea wire:model="address" class="form-control" rows="3" placeholder="Direccion operativa de la sucursal"></textarea>
                            @error('address') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="rounded-4 border border-slate-200 p-3 flex items-start gap-3">
                                <input type="checkbox" wire:model="is_active" class="mt-1">
                                <span>
                                    <span class="d-block fw-semibold text-slate-900">Sucursal activa</span>
                                    <span class="text-sm text-muted">Solo sucursales activas deben usarse para personal operativo.</span>
                                </span>
                            </label>
                            <label class="rounded-4 border border-slate-200 p-3 flex items-start gap-3">
                                <input type="checkbox" wire:model="is_default" class="mt-1">
                                <span>
                                    <span class="d-block fw-semibold text-slate-900">Sucursal default</span>
                                    <span class="text-sm text-muted">Se usa para backfill y como fallback en nuevos registros sin sucursal explícita.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <flux:button type="button" wire:click="createBranch" variant="outline" icon="arrow-path">
                        Limpiar
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="building-storefront" wire:loading.attr="disabled">
                        Guardar sucursal
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</div>
