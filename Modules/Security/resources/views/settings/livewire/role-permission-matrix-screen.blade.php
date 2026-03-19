<div class="space-y-6">
    @php
        $selectedPermissionCount = count($selectedPermissionIds);
        $modulesWithAccess = collect($moduleAccessLevels)->filter(fn ($level) => $level !== 'none')->count();
        $visibleModules = collect($moduleNavigationVisibility)->filter(fn ($isVisible) => (bool) $isVisible)->count();
        $hasUnsavedChanges = $this->hasUnsavedChanges();
    @endphp

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

                @if($selectedRole)
                    <div class="rounded-4 border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-muted">Resumen del rol</div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-2xl font-semibold text-slate-900">{{ $selectedPermissionCount }}</div>
                                <div class="text-sm text-muted">Permisos activos</div>
                            </div>
                            <div>
                                <div class="text-2xl font-semibold text-slate-900">{{ $modulesWithAccess }}</div>
                                <div class="text-sm text-muted">Modulos operativos</div>
                            </div>
                            <div>
                                <div class="text-2xl font-semibold text-slate-900">{{ $visibleModules }}</div>
                                <div class="text-sm text-muted">Items en sidebar</div>
                            </div>
                            <div>
                                <div class="text-2xl font-semibold text-slate-900">{{ $selectedRole->users_count ?? 0 }}</div>
                                <div class="text-sm text-muted">Usuarios impactados</div>
                            </div>
                        </div>
                    </div>
                @endif

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
                            <span class="badge {{ $hasUnsavedChanges ? 'bg-warning text-dark' : 'bg-success' }}">{{ $hasUnsavedChanges ? 'Cambios sin guardar' : 'Sincronizado' }}</span>
                        </div>
                    </div>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="card border-0 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="border-bottom px-4 py-3">
                                <div class="d-flex flex-wrap justify-content-between gap-3">
                                    <div>
                                        <h4 class="mb-1 text-lg font-semibold text-slate-900">Acceso por modulo</h4>
                                        <p class="mb-0 text-sm text-muted">Controla si el rol ve el modulo, el nivel operativo y su visibilidad en el menu.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="applyAccessPreset('readonly')">
                                            Todo lectura
                                        </flux:button>
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="applyAccessPreset('full')">
                                            Todo full
                                        </flux:button>
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="applyAccessPreset('none')">
                                            Limpiar acceso
                                        </flux:button>
                                    </div>
                                </div>
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
                            <div class="d-flex flex-wrap justify-content-between gap-3">
                                <div>
                                    <h4 class="mb-1 text-lg font-semibold text-slate-900">Permisos operativos</h4>
                                    <p class="mb-0 text-sm text-muted">Activa o desactiva acciones puntuales por cada modulo y recurso.</p>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="form-label">Buscar permiso</label>
                                        <flux:input wire:model.live.debounce.250ms="permissionSearch" placeholder="Codigo, recurso o accion" />
                                    </div>
                                    <div>
                                        <label class="form-label">Filtrar modulo</label>
                                        <select wire:model.live="moduleFilter" class="form-select">
                                            <option value="all">Todos los modulos</option>
                                            @foreach($modules as $module)
                                                <option value="{{ $module->code }}">{{ $module->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                @forelse($filteredModules as $module)
                                    <div class="rounded-4 border border-slate-200 p-4" wire:key="permission-group-{{ $module->id }}">
                                        <div class="mb-3 d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold text-slate-900">{{ $module->name }}</div>
                                                <div class="text-sm text-muted">{{ $module->permissions->count() }} permisos definidos</div>
                                            </div>
                                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                                <span class="badge badge-light">{{ $module->code }}</span>
                                                @if($module->permissions->isNotEmpty())
                                                    <flux:button type="button" variant="ghost" size="sm" wire:click="selectModulePermissions({{ $module->id }})">
                                                        Marcar todo
                                                    </flux:button>
                                                    <flux:button type="button" variant="ghost" size="sm" wire:click="clearModulePermissions({{ $module->id }})">
                                                        Limpiar
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            @forelse($module->permissions as $permission)
                                                <label class="flex items-start gap-3 rounded-3 border border-slate-100 px-3 py-2 text-sm">
                                                    <input type="checkbox" wire:model="selectedPermissionIds" value="{{ $permission->id }}" class="mt-1">
                                                    <span>
                                                        <span class="d-block fw-semibold text-slate-800">{{ $permission->resource }} - {{ $permission->action }}</span>
                                                        <span class="text-muted">{{ $permission->code }}</span>
                                                    </span>
                                                </label>
                                            @empty
                                                <div class="text-sm text-muted">No hay permisos que coincidan con el filtro actual.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-4 border border-dashed p-4 text-sm text-muted lg:col-span-2">
                                        No hay modulos o permisos que coincidan con los filtros aplicados.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                        <div class="text-sm text-muted">
                            {{ $selectedPermissionCount }} permisos seleccionados en {{ $modulesWithAccess }} modulos con acceso.
                        </div>
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
