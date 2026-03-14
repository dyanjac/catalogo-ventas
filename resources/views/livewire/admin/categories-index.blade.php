<div class="space-y-6">
    <x-admin.page-header
        title="Administrar categorias"
        description="Estructura el catálogo comercial y organiza la navegación de productos."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.categories.create') }}" variant="primary" icon="plus">
                Nueva categoria
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Slug</th>
                            <th>Productos</th>
                            <th>Descripcion</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr wire:key="category-{{ $category->id }}">
                                <td class="fw-semibold text-slate-800">{{ $category->name }}</td>
                                <td>{{ $category->slug }}</td>
                                <td>{{ $category->products_count }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($category->description, 90) ?: '-' }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <flux:button href="{{ route('admin.categories.edit', $category) }}" variant="primary" size="sm">
                                            Editar
                                        </flux:button>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoria?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-muted">No hay categorias registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $categories->links() }}
    </div>
</div>
