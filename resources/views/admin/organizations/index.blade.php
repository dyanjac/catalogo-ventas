@extends('layouts.admin')

@section('title', 'Organizaciones SaaS')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Organizaciones SaaS" description="Provisiona nuevas organizaciones en entorno DEMO y revisa su estado inicial.">
            <a href="{{ route('admin.organizations.create') }}" class="btn btn-primary rounded-pill px-4">
                Nueva organización demo
            </a>
        </x-admin.page-header>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Organización</th>
                                <th>Código</th>
                                <th>Entorno</th>
                                <th>Estado</th>
                                <th>Admin inicial</th>
                                <th>Creación</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $organization)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $organization->name }}</div>
                                        <div class="text-muted small">{{ $organization->slug }}</div>
                                    </td>
                                    <td>{{ $organization->code }}</td>
                                    <td>
                                        <span class="badge {{ $organization->environment === 'demo' ? 'bg-warning text-dark' : 'bg-success' }}">
                                            {{ strtoupper($organization->environment) }}
                                        </span>
                                    </td>
                                    <td>{{ strtoupper($organization->status) }}</td>
                                    <td>{{ optional($organization->users()->orderBy('id')->first())->email ?: '-' }}</td>
                                    <td>{{ optional($organization->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.organizations.show', $organization) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        No hay organizaciones provisionadas todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $organizations->links() }}
        </div>
    </div>
</div>
@endsection
