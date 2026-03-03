@extends('layouts.admin')

@section('title', 'Dashboard CMS')
@section('page_title', 'Dashboard CMS')

@section('content')
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['customers'] }}</h3>
                <p>Clientes registrados</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <a href="{{ route('admin.customers.index') }}" class="small-box-footer">Gestionar <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $stats['orders'] }}</h3>
                <p>Pedidos totales</p>
            </div>
            <div class="icon"><i class="fas fa-receipt"></i></div>
            <a href="{{ route('admin.orders.index') }}" class="small-box-footer">Revisar <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['products'] }}</h3>
                <p>Productos activos en catalogo</p>
            </div>
            <div class="icon"><i class="fas fa-boxes-stacked"></i></div>
            <a href="{{ route('admin.products.index') }}" class="small-box-footer">Catalogo <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $stats['lowStock'] }}</h3>
                <p>Productos con stock bajo</p>
            </div>
            <div class="icon"><i class="fas fa-triangle-exclamation"></i></div>
            <a href="{{ route('admin.products.index', ['is_active' => 1]) }}" class="small-box-footer">Controlar <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">Pedidos recientes</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestOrders as $order)
                            <tr>
                                <td>{{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</td>
                                <td>
                                    <div>{{ $order->user?->name ?? 'Sin usuario' }}</div>
                                    <small class="text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</small>
                                </td>
                                <td><span class="badge badge-light">{{ strtoupper($order->status) }}</span></td>
                                <td><span class="badge badge-secondary">{{ strtoupper($order->payment_status) }}</span></td>
                                <td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">Abrir</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">Aun no se registran pedidos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header border-0">
                <h3 class="card-title">Resumen de catalogo</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>Categorias</span>
                    <strong>{{ $stats['categories'] }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Unidades</span>
                    <strong>{{ $stats['unitMeasures'] }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Productos con alerta</span>
                    <strong>{{ $stats['lowStock'] }}</strong>
                </div>
                <hr>
                <div class="list-group list-group-flush">
                    @forelse($lowStockProducts as $product)
                        <a href="{{ route('admin.products.edit', $product) }}" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="font-weight-semibold">{{ $product->name }}</div>
                                    <small class="text-muted">{{ $product->category?->name ?? 'Sin categoria' }} · {{ $product->unitMeasure?->name ?? '-' }}</small>
                                </div>
                                <span class="badge badge-danger align-self-start">{{ $product->stock }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="text-muted">No hay alertas de stock por ahora.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

