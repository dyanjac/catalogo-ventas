<div class="space-y-6">
    <x-admin.page-header
        title="Accesos de usuarios"
        description="Asigna roles operativos y una sucursal base a cada cuenta. El alcance branch se resuelve usando branch_id real."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.security.roles.index') }}" variant="outline" icon="shield-check">
                Roles y permisos
            </flux:button>
            <flux:button href="{{ route('admin.security.branches.index') }}" variant="outline" icon="building-storefront">
                Sucursales
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.2fr,1fr]">
        <div class="space-y-4">
            <div class="card border-0">
                <div class="card-body">
                    <label class="form-label">Buscar usuario</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Nombre, correo, celular o documento" />
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Sucursal</th>
                                    <th>Roles activos</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                    <tr wire:key="security-user-{{ $user->id }}">
                                        <td>
                                            <div class="fw-semibold text-slate-900">{{ $user->name }}</div>
                                            <small class="text-muted">{{ $user->email }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-light">{{ $user->branch?->name ?? 'Sin sucursal' }}</span>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-2">
                                                @forelse($user->roles->filter(fn ($role) => (bool) data_get($role, 'pivot.is_active', false)) as $role)
                                                    <span class="badge badge-light">{{ $role->name }}</span>
                                                @empty
                                                    <span class="text-sm text-muted">Sin roles RBAC</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <flux:button type="button" wire:click="selectUser({{ $user->id }})" variant="{{ $selectedUser?->id === $user->id ? 'primary' : 'outline' }}" size="sm">
                                                Gestionar
                                            </flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-5 text-center text-muted">No hay usuarios para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                {{ $users->links() }}
            </div>
        </div>

        <div>
            @if($selectedUser)
                <form wire:submit="save" class="space-y-4">
                    <div class="card border-0">
                        <div class="card-body space-y-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="text-uppercase text-xs tracking-[0.3em] text-primary">Usuario activo</div>
                                    <h3 class="mb-1 text-2xl font-semibold text-slate-900">{{ $selectedUser->name }}</h3>
                                    <p class="mb-0 text-muted">{{ $selectedUser->email }}</p>
                                </div>
                                <span class="badge {{ $selectedUser->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $selectedUser->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="form-label">Sucursal base</label>
                                    <select wire:model="selectedBranchId" class="form-select">
                                        <option value="">Sin sucursal</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="rounded-3 bg-slate-50 px-3 py-2 text-sm text-muted">
                                    El scope <code>branch</code> usa esta sucursal para filtrar clientes, pedidos, comprobantes e inventario.
                                </div>
                            </div>

                            <div class="rounded-4 border border-slate-200 p-3 text-sm text-muted">
                                El campo legado <code>users.role</code> queda solo como compatibilidad. El acceso real del admin se resuelve desde <code>security_user_roles</code>.
                            </div>
                        </div>
                    </div>

                    <div class="card border-0">
                        <div class="card-body space-y-4">
                            <div>
                                <h4 class="mb-1 text-lg font-semibold text-slate-900">Roles asignados</h4>
                                <p class="mb-0 text-sm text-muted">Selecciona los roles RBAC activos para este usuario y define el alcance operativo de cada uno.</p>
                            </div>

                            <div class="space-y-3">
                                @foreach($roles as $role)
                                    <div class="rounded-4 border border-slate-200 p-3" wire:key="role-assignment-{{ $role->id }}">
                                        <div class="grid gap-3 lg:grid-cols-[1.4fr,220px] lg:items-center">
                                            <div>
                                                <label class="inline-flex items-start gap-3">
                                                    <input type="checkbox" wire:model="selectedRoleIds" value="{{ $role->id }}" class="mt-1">
                                                    <span>
                                                        <span class="d-block fw-semibold text-slate-900">{{ $role->name }}</span>
                                                        <span class="text-sm text-muted">{{ $role->code }}{{ $role->description ? ' · '.$role->description : '' }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                            <div>
                                                <label class="form-label">Scope</label>
                                                <select wire:model="roleScopes.{{ $role->id }}" class="form-select" @disabled(! in_array($role->id, $selectedRoleIds))>
                                                    <option value="all">All</option>
                                                    <option value="branch">Branch</option>
                                                    <option value="own">Own</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <flux:button type="submit" variant="primary" icon="users" wire:loading.attr="disabled">
                            Guardar accesos del usuario
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="card border-0">
                    <div class="card-body py-5 text-center text-muted">
                        Selecciona un usuario para asignar roles, scope y sucursal base.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
