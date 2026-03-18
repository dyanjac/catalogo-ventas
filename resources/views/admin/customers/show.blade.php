@extends('layouts.admin')

@section('title', 'Gestionar cliente')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header
            title="Gestionar Cliente"
            :description="$customer->name . ' · ' . $customer->email"
        >
            <x-slot:actions>
                <x-admin.action-bar>
                    <a href="{{ route('admin.security.users.index', ['search' => $customer->email]) }}" class="btn btn-outline-primary rounded-pill px-4">Gestion RBAC</a>
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
                </x-admin.action-bar>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="row g-4">
            <div class="col-lg-7">
                <x-admin.form-card
                    :action="route('admin.customers.update', $customer)"
                    method="PUT"
                    submit-label="Guardar cambios"
                    :cancel-href="route('admin.customers.index')"
                    class="h-100"
                >
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
                            <label class="form-label">Roles RBAC</label>
                            <div class="rounded-3 border bg-light px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($customer->roles as $role)
                                        <span class="badge {{ $role->code === 'super_admin' ? 'bg-dark' : 'bg-primary' }}">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-muted">Sin roles asignados.</span>
                                    @endforelse
                                </div>
                                <small class="text-muted d-block mt-2">La asignacion de roles y accesos se administra desde Seguridad &gt; Accesos de usuarios.</small>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
                                <label class="form-check-label" for="is_active">Usuario activo</label>
                            </div>
                        </div>
                    </div>
                </x-admin.form-card>
            </div>
            <div class="col-lg-5">
                <x-admin.info-card title="Pedidos recientes" class="h-100">
                    <div class="list-group list-group-flush">
                        @forelse($customer->orders as $order)
                            <a href="{{ route('admin.orders.show', $order) }}" class="list-group-item list-group-item-action px-0">
                                <x-admin.detail-grid
                                    :items="[
                                        ['label' => 'Pedido', 'value' => $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT), 'class' => 'col-8'],
                                        ['label' => 'Estado', 'value' => strtoupper($order->status), 'class' => 'col-4'],
                                        ['label' => 'Fecha', 'value' => $order->created_at?->format('d/m/Y H:i'), 'class' => 'col-6'],
                                        ['label' => 'Total', 'value' => $order->currency . ' ' . number_format((float) $order->total, 2), 'class' => 'col-6'],
                                    ]"
                                    columns="col-6"
                                />
                            </a>
                        @empty
                            <div class="text-muted">Este usuario aun no registra pedidos.</div>
                        @endforelse
                    </div>
                </x-admin.info-card>
            </div>
        </div>
    </div>
</div>
@endsection
