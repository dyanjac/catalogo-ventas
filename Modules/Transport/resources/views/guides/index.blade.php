@extends('layouts.admin')

@section('title', 'Guias de remision')

@php($transportAuthorization = app(\Modules\Security\Services\SecurityAuthorizationService::class))

@section('content')
<div class="py-2">
    <x-admin.page-header title="Guias de remision electronicas">
        <x-slot:actions>
            @if($transportAuthorization->hasPermission(auth()->user(), 'transport.guides.create'))
                <a href="{{ route('admin.transport.guides.create') }}" class="btn btn-primary">Nueva GRE</a>
            @endif
            @if($transportAuthorization->hasPermission(auth()->user(), 'transport.settings.configure'))
                <a href="{{ route('admin.transport.settings.edit') }}" class="btn btn-outline-secondary">Configuracion</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>GRE</th><th>Tipo</th><th>Motivo</th><th>Sucursal</th><th>Estado</th><th>Traslado</th><th></th></tr></thead>
                <tbody>
                @forelse($guides as $guide)
                    <tr>
                        <td>{{ $guide->formattedNumber() }}</td>
                        <td>{{ $guide->guide_type->value === 'sender' ? 'Remitente' : 'Transportista' }}</td>
                        <td>{{ config('transport.reasons.'.$guide->reason_code, $guide->reason_code) }}</td>
                        <td>{{ $guide->branch?->name }}</td>
                        <td><span class="badge text-bg-secondary">{{ $guide->status->value }}</span></td>
                        <td>{{ $guide->transfer_at?->format('d/m/Y H:i') }}</td>
                        <td><a href="{{ route('admin.transport.guides.show', $guide) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay guias registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $guides->links() }}</div>
    </div>
</div>
@endsection
