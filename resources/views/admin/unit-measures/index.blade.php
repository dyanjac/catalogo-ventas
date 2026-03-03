@extends('layouts.admin')

@section('title', 'Admin - Unidades')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Administrar Unidades</h1>
            <a href="{{ route('admin.unit-measures.create') }}" class="btn btn-primary rounded-pill px-4">Nueva unidad</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Productos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unitMeasures as $unitMeasure)
                        <tr>
                            <td>{{ $unitMeasure->name }}</td>
                            <td>{{ $unitMeasure->products_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.unit-measures.edit', $unitMeasure) }}" class="btn btn-sm btn-primary">Editar</a>
                                <form method="POST" action="{{ route('admin.unit-measures.destroy', $unitMeasure) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta unidad?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">No hay unidades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $unitMeasures->links() }}
        </div>
    </div>
</div>
@endsection

