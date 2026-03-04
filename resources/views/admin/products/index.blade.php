@extends('layouts.admin')

@section('title', 'Admin - Productos')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header
            title="Administrar Productos"
            action-label="Nuevo producto"
            :action-href="route('admin.products.create')"
        />

        <x-admin.filter-card>
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar (nombre o SKU)</label>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría</label>
                        <select name="category_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unidad</label>
                        <select name="unit_measure_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($unitMeasures as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('unit_measure_id') === (string) $unit->id)>
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Activo</label>
                        <select name="is_active" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" @selected(request('is_active') === '1')>Sí</option>
                            <option value="0" @selected(request('is_active') === '0')>No</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Filtrar</button>
                        <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
                    </div>
                </div>
            </form>
        </x-admin.filter-card>

        <x-admin.data-table :colspan="11" empty-message="No hay productos para mostrar.">
            <x-slot:head>
                    <tr>
                        <th>SKU</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Unidad</th>
                        <th>Precio venta</th>
                        <th>Precio mayor</th>
                        <th>Stock</th>
                        <th>Stock mínimo</th>
                        <th>Afectación</th>
                        <th>Activo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </x-slot:head>
            @forelse($products as $product)
                    <tr>
                        <td>{{ $product->sku ?? '-' }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? '-' }}</td>
                        <td>{{ $product->unitMeasure?->name ?? '-' }}</td>
                        <td>S/ {{ number_format((float) ($product->sale_price ?? 0), 2) }}</td>
                        <td>S/ {{ number_format((float) ($product->wholesale_price ?? 0), 2) }}</td>
                        <td>
                            {{ $product->stock }}
                            @if($product->stock <= $product->min_stock)
                                <span class="badge bg-danger ms-1">Bajo</span>
                            @endif
                        </td>
                        <td>{{ $product->min_stock }}</td>
                        <td>{{ $product->tax_affectation }}</td>
                        <td>
                            @if($product->is_active)
                                <span class="badge bg-success">Sí</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-light border">Ver</a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">Editar</a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
            @empty
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$products" />
    </div>
</div>
@endsection

