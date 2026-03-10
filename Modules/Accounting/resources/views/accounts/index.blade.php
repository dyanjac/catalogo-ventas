@extends('layouts.admin')

@section('title', 'Plan de cuentas')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Plan de cuentas">
            <x-slot:actions>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('admin.accounting.accounts.setup-default-sales-chart') }}">
                        @csrf
                        <button class="btn btn-light border rounded-pill px-4">Configurar plan mínimo de ventas</button>
                    </form>
                    <form method="POST" action="{{ route('admin.accounting.accounts.reset-chart') }}" onsubmit="return confirm('Se eliminarán todas las cuentas contables y su configuración en productos. ¿Deseas continuar?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger rounded-pill px-4">Eliminar plan contable</button>
                    </form>
                </div>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.accounting.accounts.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label">Código</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cuenta padre</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Nivel</label>
                        <input type="number" min="1" max="9" name="level" class="form-control" value="1">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary rounded-pill px-4">Agregar cuenta</button>
                    </div>

                    <div class="col-12 d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label" for="is_active">Activa</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_default_sales" value="0">
                            <input class="form-check-input" type="checkbox" id="is_default_sales" name="is_default_sales" value="1">
                            <label class="form-check-label" for="is_default_sales">Defecto ventas</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_default_purchase" value="0">
                            <input class="form-check-input" type="checkbox" id="is_default_purchase" name="is_default_purchase" value="1">
                            <label class="form-check-label" for="is_default_purchase">Defecto compras</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_default_tax" value="0">
                            <input class="form-check-input" type="checkbox" id="is_default_tax" name="is_default_tax" value="1">
                            <label class="form-check-label" for="is_default_tax">Defecto impuesto</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="is_default_receivable" value="0">
                            <input class="form-check-input" type="checkbox" id="is_default_receivable" name="is_default_receivable" value="1">
                            <label class="form-check-label" for="is_default_receivable">Defecto cta. cobrar</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-secondary rounded-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Padre</th>
                            <th class="text-center">Nivel</th>
                            <th class="text-center">Activa</th>
                            <th class="text-end">Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <form method="POST" action="{{ route('admin.accounting.accounts.update', $account) }}">
                                    @csrf
                                    @method('PUT')
                                    <td><input type="text" name="code" class="form-control" value="{{ $account->code }}" required></td>
                                    <td><input type="text" name="name" class="form-control" value="{{ $account->name }}" required></td>
                                    <td>
                                        <select name="type" class="form-select" required>
                                            @foreach($types as $type)
                                                <option value="{{ $type }}" @selected($account->type === $type)>{{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="parent_id" class="form-select">
                                            <option value="">-</option>
                                            @foreach($parents as $parent)
                                                @if($parent->id !== $account->id)
                                                    <option value="{{ $parent->id }}" @selected((int) $account->parent_id === $parent->id)>{{ $parent->code }} - {{ $parent->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" min="1" max="9" name="level" class="form-control text-center" value="{{ $account->level }}"></td>
                                    <td class="text-center">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($account->is_active)>
                                    </td>
                                    <td class="text-end">
                                        <input type="hidden" name="is_default_sales" value="{{ $account->is_default_sales ? 1 : 0 }}">
                                        <input type="hidden" name="is_default_purchase" value="{{ $account->is_default_purchase ? 1 : 0 }}">
                                        <input type="hidden" name="is_default_tax" value="{{ $account->is_default_tax ? 1 : 0 }}">
                                        <input type="hidden" name="is_default_receivable" value="{{ $account->is_default_receivable ? 1 : 0 }}">
                                        <button class="btn btn-sm btn-primary rounded-pill px-3">Guardar</button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-muted">No hay cuentas contables registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $accounts->links() }}</div>
        </div>
    </div>
</div>
@endsection
