@extends('layouts.admin')

@section('title', 'Gestionar cliente')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-primary mb-0">Gestionar Cliente</h1>
                <p class="text-muted mb-0">{{ $customer->name }} · {{ $customer->email }}</p>
            </div>
            <a href="{{ route('admin.customers.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="card border border-secondary rounded-3 h-100">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Celular</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo documento</label>
                                <select name="document_type" class="form-select">
                                    <option value="">Sin definir</option>
                                    <option value="dni" @selected(old('document_type', $customer->document_type) === 'dni')>DNI</option>
                                    <option value="ruc" @selected(old('document_type', $customer->document_type) === 'ruc')>RUC</option>
                                    <option value="ce" @selected(old('document_type', $customer->document_type) === 'ce')>CE</option>
                                    <option value="pasaporte" @selected(old('document_type', $customer->document_type) === 'pasaporte')>Pasaporte</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Numero documento</label>
                                <input type="text" name="document_number" class="form-control" value="{{ old('document_number', $customer->document_number) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ciudad</label>
                                <input type="text" name="city" class="form-control" value="{{ old('city', $customer->city) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Direccion</label>
                                <input type="text" name="address" class="form-control" value="{{ old('address', $customer->address) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Perfil</label>
                                <select name="role" class="form-select">
                                    <option value="customer" @selected(old('role', $customer->role) === 'customer')>Cliente</option>
                                    <option value="super_admin" @selected(old('role', $customer->role) === 'super_admin')>Super usuario</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                    <label class="form-check-label" for="is_active">Usuario activo</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar cambios</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="card border border-secondary rounded-3 h-100">
                    <div class="card-body">
                        <h4 class="text-primary">Pedidos recientes</h4>
                        <div class="list-group list-group-flush">
                            @forelse($customer->orders as $order)
                                <a href="{{ route('admin.orders.show', $order) }}" class="list-group-item list-group-item-action px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold">{{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</div>
                                            <small class="text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</small>
                                        </div>
                                        <span class="badge bg-light text-dark">{{ strtoupper($order->status) }}</span>
                                    </div>
                                    <div class="mt-2 text-primary fw-semibold">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</div>
                                </a>
                            @empty
                                <div class="text-muted">Este usuario aun no registra pedidos.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

