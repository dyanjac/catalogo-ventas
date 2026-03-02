@extends('layouts.app')

@section('title', 'Mi Carrito')

@section('content')
<div class="container py-5">
    <h1 class="mb-4 text-primary">Mi Carrito</h1>

    @include('partials.flash')

    @if(empty($cart))
        <div class="alert alert-light border">Tu carrito está vacío.</div>
        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">Ir al catálogo</a>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img
                                                src="{{ $item['image'] ? asset('storage/' . $item['image']) : asset('img/hero-img-1.png') }}"
                                                alt="{{ $item['name'] }}"
                                                style="width: 64px; height: 64px; object-fit: cover;"
                                                class="rounded"
                                            >
                                            <div>
                                                <div class="fw-semibold">{{ $item['name'] }}</div>
                                                <small class="text-muted">Código: {{ $item['id'] }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('cart.update', $item['id']) }}" class="d-inline-flex align-items-center gap-2">
                                            @csrf
                                            <input type="number" min="1" name="quantity" value="{{ $item['quantity'] }}" class="form-control form-control-sm" style="width: 78px;">
                                            <button class="btn btn-sm btn-light border">Actualizar</button>
                                        </form>
                                    </td>
                                    <td class="text-end">S/ {{ number_format((float) $item['price'], 2) }}</td>
                                    <td class="text-end fw-semibold">S/ {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('cart.remove', $item['id']) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-danger">Quitar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('cart.clear') }}" class="mt-2">
                    @csrf
                    <button class="btn btn-outline-danger">Vaciar carrito</button>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card border border-secondary">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Resumen del carrito</h5>
                        <p class="mb-1 d-flex justify-content-between"><span>Subtotal</span><strong id="summary-subtotal">S/ {{ number_format((float) $total, 2) }}</strong></p>
                        <p class="mb-1 d-flex justify-content-between"><span>Descuento</span><strong id="summary-discount">S/ 0.00</strong></p>
                        <p class="mb-1 d-flex justify-content-between"><span>IGV (18%)</span><strong id="summary-tax">S/ {{ number_format((float) $total * 0.18, 2) }}</strong></p>
                        <p class="mb-3 d-flex justify-content-between"><span>Envío</span><strong id="summary-shipping">S/ 0.00</strong></p>
                        <hr>
                        <p class="mb-4 d-flex justify-content-between fs-5"><span>Total</span><strong id="summary-total">S/ {{ number_format((float) $total * 1.18, 2) }}</strong></p>

                        @auth
                            <a href="{{ route('checkout.show') }}" class="btn btn-primary w-100">Ir al checkout</a>
                        @else
                            <div class="alert alert-warning mb-2">
                                Debes iniciar sesión para confirmar y grabar el pedido.
                            </div>
                            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#authModal">
                                Iniciar sesión para continuar
                            </button>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
