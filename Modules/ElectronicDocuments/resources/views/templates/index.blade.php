@extends('layouts.admin')

@section('title', 'Plantillas XSLT')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Plantillas XSLT para comprobantes" description="Las plantillas se administran por organización y no se comparten entre tenants.">
            <x-slot:actions>
                <a href="{{ route('admin.electronic-documents.templates.create') }}" class="btn btn-primary rounded-pill px-4">Nueva plantilla</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3 mb-4">
            <div class="card-header bg-light">
                <h3 class="card-title mb-0">Previsualizar plantilla</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.electronic-documents.templates.preview') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Plantilla</label>
                        <select name="template_id" class="form-select" required>
                            <option value="">Selecciona</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">
                                    {{ $template->name }} ({{ strtoupper($template->document_type) }}){{ $template->is_active ? '' : ' - INACTIVA' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">XML de prueba</label>
                        <select name="xml_path" class="form-select" required>
                            <option value="">Selecciona</option>
                            @foreach($sampleXmlOptions as $sample)
                                <option value="{{ $sample['path'] }}">{{ $sample['label'] }} · {{ $sample['path'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-light border rounded-pill px-4">Previsualizar HTML</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-secondary rounded-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->id }}</td>
                            <td>{{ $template->name }}</td>
                            <td>{{ strtoupper($template->document_type) }}</td>
                            <td>
                                <span class="badge {{ $template->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $template->is_active ? 'ACTIVA' : 'INACTIVA' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.electronic-documents.templates.toggle', $template) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning border" title="Activar / desactivar">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <a href="{{ route('admin.electronic-documents.templates.edit', $template) }}" class="btn btn-sm btn-light border">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form action="{{ route('admin.electronic-documents.templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar plantilla?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No hay plantillas registradas.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $templates->links() }}</div>
        </div>
    </div>
</div>
@endsection
