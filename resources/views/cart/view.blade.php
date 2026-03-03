@extends('layouts.app')

@section('title', 'Mi Carrito')

@section('content')
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Carrito comercial</span>
                <h1>Revisa tu pedido antes de confirmar</h1>
                <p>Controla cantidades, subtotal e impuestos con una vista clara orientada a cierre de compra.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="btn btn-light border rounded-pill px-4">Seguir comprando</a>
        </div>

        @include('partials.flash')

        @if(empty($cart))
            <div class="mp-empty-state">
                <h3>Tu carrito esta vacio</h3>
                <p>Explora el catalogo y agrega productos de alta rotacion para continuar con tu pedido.</p>
                <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">Ir al catalogo</a>
            </div>
        @else
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="mp-cart-panel">
                        @foreach ($cart as $item)
                            <div class="mp-cart-item">
                                <div class="mp-cart-media">
                                    <img
                                        src="{{ $item['image'] ? asset('storage/' . $item['image']) : asset('img/hero-img-1.png') }}"
                                        alt="{{ $item['name'] }}"
                                    >
                                </div>
                                <div class="mp-cart-content">
                                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <h5 class="mb-1">{{ $item['name'] }}</h5>
                                            <p class="mb-1 text-muted">Codigo interno: {{ $item['id'] }}</p>
                                            <div class="mp-cart-price">S/ {{ number_format((float) $item['price'], 2) }} <span>por unidad</span></div>
                                        </div>
                                        <div class="text-lg-end">
                                            <div class="mp-cart-line-total">S/ {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</div>
                                            <small class="text-muted">Total parcial</small>
                                        </div>
                                    </div>

                                    <div class="mp-cart-actions">
                                        <form method="POST" action="{{ route('cart.update', $item['id']) }}" class="d-flex align-items-center gap-2 flex-wrap">
                                            @csrf
                                            <div class="input-group" style="max-width: 160px;">
                                                <span class="input-group-text">Cant.</span>
                                                <input type="number" min="1" name="quantity" value="{{ $item['quantity'] }}" class="form-control">
                                            </div>
                                            <button class="btn btn-light border rounded-pill px-3">Actualizar</button>
                                        </form>

                                        <form method="POST" action="{{ route('cart.remove', $item['id']) }}">
                                            @csrf
                                            <button class="btn btn-outline-danger rounded-pill px-3">Quitar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <form method="POST" action="{{ route('cart.clear') }}" class="mt-4">
                            @csrf
                            <button class="btn btn-outline-danger rounded-pill px-4">Vaciar carrito</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="mp-cart-summary">
                        <span class="mp-kicker">Resumen</span>
                        <h4 class="mb-3">Totales del pedido</h4>
                        <div class="mp-summary-row"><span>Subtotal</span><strong>S/ {{ number_format((float) $total, 2) }}</strong></div>
                        <div class="mp-summary-row"><span>Descuento</span><strong>S/ 0.00</strong></div>
                        <div class="mp-summary-row"><span>IGV (18%)</span><strong>S/ {{ number_format((float) $total * 0.18, 2) }}</strong></div>
                        <div class="mp-summary-row"><span>Envio</span><strong>S/ 0.00</strong></div>
                        <div class="mp-summary-row mp-summary-total"><span>Total</span><strong>S/ {{ number_format((float) $total * 1.18, 2) }}</strong></div>

                        <div class="mp-info-strip mt-4 mb-4">
                            <div class="mp-info-chip"><i class="fa fa-shield-alt"></i><span>Compra segura</span></div>
                            <div class="mp-info-chip"><i class="fa fa-headset"></i><span>Soporte comercial</span></div>
                        </div>

                        @auth
                            <a href="{{ route('checkout.show') }}" class="btn btn-primary btn-lg rounded-pill w-100">Ir al checkout</a>
                        @else
                            <div class="alert alert-warning mb-3">
                                Debes iniciar sesión para confirmar y grabar el pedido.
                            </div>
                            <button type="button" class="btn btn-primary btn-lg rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#authModal">
                                Iniciar sesion para continuar
                            </button>
                        @endauth
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
