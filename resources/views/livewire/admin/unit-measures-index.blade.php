<div class="space-y-6">
    <x-admin.page-header
        title="Administrar unidades"
        description="Mantén consistencia operativa para inventario, ventas y facturación."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.unit-measures.create') }}" variant="primary" icon="plus">
                Nueva unidad
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
                            <th>Productos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($unitMeasures as $unitMeasure)
                            <tr wire:key="unit-{{ $unitMeasure->id }}">
                                <td class="fw-semibold text-slate-800">{{ $unitMeasure->name }}</td>
                                <td>{{ $unitMeasure->products_count }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <flux:button href="{{ route('admin.unit-measures.edit', $unitMeasure) }}" variant="primary" size="sm">
                                            Editar
                                        </flux:button>
                                        <form method="POST" action="{{ route('admin.unit-measures.destroy', $unitMeasure) }}" onsubmit="return confirm('¿Eliminar esta unidad?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-5 text-center text-muted">No hay unidades registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $unitMeasures->links() }}
    </div>
</div>
