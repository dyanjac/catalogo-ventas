<div class="space-y-6">
    <x-admin.page-header
        title="Roles y permisos"
        description="Define el acceso por modulo, navegacion y permisos operativos de cada rol del sistema."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.security.users.index') }}" variant="outline" icon="users">
                Accesos de usuarios
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[320px,1fr]">
        <div class="card border-0">
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label">Buscar rol</label>
                    <flux:input wire:model.live.debounce.300ms="roleSearch" placeholder="Codigo o nombre del rol" />
                </div>

                <div class="space-y-2">
                    @forelse($roles as $role)
                        <button type="button"
                            wire:click="selectRole({{ $role->id }})"
                            class="w-full rounded-4 border px-3 py-3 text-start transition {{ $selectedRole?->id === $role->id ? 'border-primary bg-primary-subtle' : 'border-slate-200 bg-white hover:border-primary-subtle' }}">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold text-slate-900">{{ $role->name }}</div>
                                    <div class="text-sm text-muted">{{ $role->code }}</div>
                                </div>
                                <span class="badge {{ $role->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $role->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2 text-sm text-muted">
                                <span>{{ $role->permissions_count }} permisos</span>
                                <span>{{ $role->users_count }} usuarios</span>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-4 border border-dashed p-4 text-sm text-muted">No hay roles para mostrar.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-4">
            @if($selectedRole)
                <div class="card border-0">
                    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
                        <div>
                            <div class="text-uppercase text-xs tracking-[0.3em] text-primary">Rol activo</div>
                            <h3 class="mb-1 text-2xl font-semibold text-slate-900">{{ $selectedRole->name }}</h3>
                            <p class="mb-0 text-muted">{{ $selectedRole->description ?: 'Sin descripcion operativa.' }}</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            <span class="badge bg-dark">{{ $selectedRole->code }}</span>
                            <span class="badge {{ $selectedRole->is_system ? 'bg-info' : 'bg-secondary' }}">{{ $selectedRole->is_system ? 'Sistema' : 'Personalizado' }}</span>
                        </div>
                    </div>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="card border-0 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="border-bottom px-4 py-3">
                                <h4 class="mb-1 text-lg font-semibold text-slate-900">Acceso por modulo</h4>
                                <p class="mb-0 text-sm text-muted">Controla si el rol ve el modulo, el nivel operativo y su visibilidad en el menu.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="table mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Modulo</th>
                                            <th>Estado</th>
                                            <th>Nivel de acceso</th>
                                            <th>Menu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($modules as $module)
                                            <tr wire:key="module-access-{{ $module->id }}">
                                                <td>
                                                    <div class="fw-semibold text-slate-800">{{ $module->name }}</div>
                                                    <small class="text-muted">{{ $module->code }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light">{{ strtoupper(str_replace('_', ' ', $module->status)) }}</span>
                                                </td>
                                                <td>
                                                    <select wire:model="moduleAccessLevels.{{ $module->id }}" class="form-select">
                                                        @foreach($accessLevelOptions as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <label class="inline-flex items-center gap-2 text-sm text-muted">
                                                        <input type="checkbox" wire:model="moduleNavigationVisibility.{{ $module->id }}">
                                                        Mostrar en sidebar
                                                    </label>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0">
                        <div class="card-body space-y-4">
                            <div>
                                <h4 class="mb-1 text-lg font-semibold text-slate-900">Permisos operativos</h4>
                                <p class="mb-0 text-sm text-muted">Activa o desactiva acciones puntuales por cada modulo y recurso.</p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach($modules as $module)
                                    <div class="rounded-4 border border-slate-200 p-4" wire:key="permission-group-{{ $module->id }}">
                                        <div class="mb-3 d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold text-slate-900">{{ $module->name }}</div>
                                                <div class="text-sm text-muted">{{ $module->permissions->count() }} permisos definidos</div>
                                            </div>
                                            <span class="badge badge-light">{{ $module->code }}</span>
                                        </div>

                                        <div class="space-y-2">
                                            @forelse($module->permissions as $permission)
                                                <label class="flex items-start gap-3 rounded-3 border border-slate-100 px-3 py-2 text-sm">
                                                    <input type="checkbox" wire:model="selectedPermissionIds" value="{{ $permission->id }}" class="mt-1">
                                                    <span>
                                                        <span class="d-block fw-semibold text-slate-800">{{ $permission->resource }} · {{ $permission->action }}</span>
                                                        <span class="text-muted">{{ $permission->code }}</span>
                                                    </span>
                                                </label>
                                            @empty
                                                <div class="text-sm text-muted">Este modulo aun no define permisos finos.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <flux:button type="submit" variant="primary" icon="shield-check" wire:loading.attr="disabled">
                            Guardar permisos del rol
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="card border-0">
                    <div class="card-body text-center text-muted py-5">Selecciona un rol para editar sus permisos y modulos.</div>
                </div>
            @endif
        </div>
    </div>
</div>
