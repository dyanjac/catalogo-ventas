@extends('layouts.admin')

@section('title', 'Admin - Unidades')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header
            title="Administrar Unidades"
            action-label="Nueva unidad"
            :action-href="route('admin.unit-measures.create')"
        />

        <x-admin.data-table :colspan="3" empty-message="No hay unidades registradas.">
            <x-slot:head>
                    <tr>
                        <th>Nombre</th>
                        <th>Productos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </x-slot:head>
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
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$unitMeasures" />
    </div>
</div>
@endsection

