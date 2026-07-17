@extends('layouts.admin')

@section('title', 'Suscripciones')

@section('content')
<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <x-admin.page-header title="Suscripciones comerciales" />
        <a href="{{ route('admin.subscriptions.create') }}" class="btn btn-primary rounded-pill">Nueva suscripciÃ³n</a>
    </div>
    <div class="card border"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>CÃ³digo</th><th>Cliente</th><th>Producto</th><th>Estado</th><th>Periodo actual</th><th>Importe</th><th></th></tr></thead>
        <tbody>@forelse($subscriptions as $subscription)<tr>
            <td>{{ $subscription->code }}</td><td>{{ $subscription->customer?->name }}</td><td>{{ $subscription->product?->name }}</td>
            <td><span class="badge text-bg-secondary">{{ $subscription->status->value }}</span></td>
            <td>{{ $subscription->current_period_starts_on->format('d/m/Y') }} â†’ {{ $subscription->current_period_ends_on->format('d/m/Y') }}</td>
            <td>{{ $subscription->currency }} {{ number_format($subscription->recurring_total_minor / 100, 2) }}</td>
            <td><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
        </tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No hay suscripciones comerciales.</td></tr>@endforelse</tbody>
    </table></div></div>
    <div class="mt-3">{{ $subscriptions->links() }}</div>
</div>
@endsection
