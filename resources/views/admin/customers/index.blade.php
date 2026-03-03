@extends('layouts.app')

@section('title', 'Admin - Clientes')

@section('content')
<div class="container-fluid py-5 mt-5">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Clientes Registrados</h1>
        </div>

        @include('partials.flash')

        <form method="GET" class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
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
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
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
                        <tr>
                            <td colspan="5" class="text-center">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $customers->links() }}
        </div>
    </div>
</div>
@endsection
