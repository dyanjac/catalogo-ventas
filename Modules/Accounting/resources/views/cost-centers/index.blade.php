@extends('layouts.admin')

@section('title', 'Centros de costo')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Centros de costo" />

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.accounting.cost-centers.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label">Código</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                            <label class="form-check-label" for="is_active">Activo</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary rounded-pill px-4">Crear centro</button>
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
                            <th>Descripción</th>
                            <th class="text-center">Activo</th>
                            <th class="text-end">Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costCenters as $costCenter)
                            <tr>
                                <form method="POST" action="{{ route('admin.accounting.cost-centers.update', $costCenter) }}">
                                    @csrf
                                    @method('PUT')
                                    <td><input type="text" name="code" class="form-control" value="{{ $costCenter->code }}" required></td>
                                    <td><input type="text" name="name" class="form-control" value="{{ $costCenter->name }}" required></td>
                                    <td><input type="text" name="description" class="form-control" value="{{ $costCenter->description }}"></td>
                                    <td class="text-center">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($costCenter->is_active)>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-primary rounded-pill px-3">Guardar</button>
                                    </td>
                                </form>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">No hay centros de costo registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $costCenters->links() }}</div>
        </div>
    </div>
</div>
@endsection
