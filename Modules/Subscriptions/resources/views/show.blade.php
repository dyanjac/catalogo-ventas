@extends('layouts.admin')

@section('title', $subscription->code)

@section('content')
<div class="container-fluid py-2"><x-admin.page-header :title="$subscription->code.' Â· '.$subscription->product->name" />
<div class="row g-3 mb-3"><div class="col-lg-8"><div class="card border"><div class="card-body">
    <div class="row g-2"><div class="col-md-4"><strong>Cliente</strong><br>{{ $subscription->customer->name }}</div><div class="col-md-4"><strong>Estado</strong><br>{{ $subscription->status->value }}</div><div class="col-md-4"><strong>Renovaciones</strong><br>{{ $subscription->renewal_count }}</div></div>
</div></div></div><div class="col-lg-4"><div class="card border"><div class="card-body d-flex gap-2">
    <form method="POST" action="{{ route('admin.subscriptions.renew', $subscription) }}">@csrf<button class="btn btn-outline-primary">Renovar</button></form>
    <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}">@csrf<input type="hidden" name="reason" value="CancelaciÃ³n administrativa"><button class="btn btn-outline-danger">Cancelar al cierre</button></form>
</div></div></div></div>
@foreach($subscription->periods->sortByDesc('sequence') as $period)<div class="card border mb-3"><div class="card-header d-flex justify-content-between">Periodo #{{ $period->sequence }} Â· {{ $period->service_starts_on->format('d/m/Y') }} â†’ {{ $period->service_ends_on->format('d/m/Y') }} @if($period->billing_document_id)<span>Comprobante #{{ $period->billing_document_id }}</span>@else<form method="POST" action="{{ route('admin.subscriptions.billing.attach', [$subscription, $period]) }}" class="d-flex gap-2">@csrf<select name="billing_document_id" class="form-select form-select-sm" required><option value="">Vincular comprobante...</option>@foreach($billingDocuments as $document)<option value="{{ $document->id }}">{{ $document->series }}-{{ $document->number }} Â· {{ $document->currency }} {{ number_format($document->total, 2) }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Vincular</button></form>@endif</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tramo [inicio, fin)</th><th>Vence</th><th>Importe</th><th>Tipo</th><th>Estado</th><th>Evento</th></tr></thead><tbody>
@foreach($period->accruals as $accrual)<tr><td>{{ $accrual->service_starts_on->format('d/m/Y') }} â†’ {{ $accrual->service_ends_on->format('d/m/Y') }}</td><td>{{ $accrual->due_on->format('d/m/Y') }}</td><td>{{ $accrual->currency }} {{ number_format($accrual->amount_minor / 100, 2) }}</td><td>{{ $accrual->kind }}</td><td>{{ $accrual->status->value }}</td><td>{{ $accrual->economicEvent?->id ?? 'â€”' }}</td></tr>@endforeach
</tbody></table></div></div>@endforeach
<div class="card border"><div class="card-body"><h6>Ajuste compensatorio</h6><form method="POST" action="{{ route('admin.subscriptions.adjust', $subscription) }}" class="row g-2">@csrf<div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control" placeholder="Importe +/-" required></div><div class="col-md-3"><input name="due_on" type="date" class="form-control" value="{{ now('UTC')->toDateString() }}" required></div><div class="col-md-5"><input name="reason" class="form-control" placeholder="Motivo auditable" required></div><div class="col-md-2"><button class="btn btn-outline-warning w-100">Programar ajuste</button></div></form></div></div>
</div>
@endsection
