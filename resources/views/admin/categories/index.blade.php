@extends('layouts.admin')

@section('title', 'Admin - Categorias')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header
            title="Administrar Categorias"
            action-label="Nueva categoria"
            :action-href="route('admin.categories.create')"
        />

        <x-admin.data-table :colspan="5" empty-message="No hay categorias registradas.">
            <x-slot:head>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Productos</th>
                        <th>Descripcion</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </x-slot:head>
            @forelse($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td>{{ $category->products_count }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($category->description, 90) ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-primary">Editar</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta categoria?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
            @empty
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$categories" />
    </div>
</div>
@endsection

