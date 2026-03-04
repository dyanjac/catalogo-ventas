@extends('layouts.admin')

@section('title', 'Admin - Clientes')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Clientes Registrados" />

        <x-admin.filter-card>
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Buscar cliente</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Nombre, correo, celular o documento">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Perfil</label>
                        <select name="role" class="form-select">
                            <option value="">Todos</option>
                            <option value="customer" @selected($role === 'customer')>Cliente</option>
                            <option value="super_admin" @selected($role === 'super_admin')>Super usuario</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Filtrar</button>
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
                    </div>
                </div>
            </form>
        </x-admin.filter-card>

        <x-admin.data-table :colspan="5" empty-message="No hay usuarios registrados.">
            <x-slot:head>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </x-slot:head>
            @forelse($customers as $customer)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $customer->name }}</div>
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
                                <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-primary">Gestionar</a>
                            </td>
                        </tr>
            @empty
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$customers" />
    </div>
</div>
@endsection

