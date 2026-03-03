@extends('layouts.app')

@section('title', 'Nosotros')

@section('content')
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="row g-4 align-items-center mb-5">
            <div class="col-lg-6">
                <span class="mp-kicker">Nuestra empresa</span>
                <h1 class="mp-detail-title">Distribucion confiable para negocios que no pueden detener su operacion</h1>
                <p class="mp-detail-copy">
                    {{ $commerce['name'] }} abastece panaderias, bodegas y negocios de alimentos con una oferta enfocada en harinas, azucares, mantecas y otros productos de consumo masivo.
                </p>
                <div class="mp-info-strip mt-4">
                    <div class="mp-info-chip"><i class="fa fa-boxes"></i><span>Stock de rotacion diaria</span></div>
                    <div class="mp-info-chip"><i class="fa fa-handshake"></i><span>Atencion personalizada</span></div>
                    <div class="mp-info-chip"><i class="fa fa-store"></i><span>Venta minorista y mayorista</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mp-detail-gallery">
                    <div class="mp-detail-main">
                        <img src="{{ asset('img/hero-img-1.png') }}" class="img-fluid w-100" alt="{{ $commerce['name'] }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="mp-category-panel">
                    <span class="mp-kicker">Mision</span>
                    <h4>Abastecer con agilidad y criterio comercial</h4>
                    <p class="mb-0">Entregar productos confiables, con precios competitivos y una atencion que facilite la compra recurrente de cada cliente.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mp-category-panel">
                    <span class="mp-kicker">Vision</span>
                    <h4>Ser referencia local en insumos de alta rotacion</h4>
                    <p class="mb-0">Consolidarnos como una marca cercana, eficiente y rentable para negocios que requieren reposicion continua.</p>
                </div>
            </div>
        </div>

        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Valor comercial</span>
                <h2>Por que los clientes nos eligen</h2>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="mp-contact-card h-100">
                    <i class="fas fa-user-tie"></i>
                    <div>
                        <h5>Atencion personalizada</h5>
                        <p class="mb-0">Recomendamos productos segun el tipo de negocio, volumen y frecuencia de compra.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mp-contact-card h-100">
                    <i class="fas fa-warehouse"></i>
                    <div>
                        <h5>Venta minorista y mayorista</h5>
                        <p class="mb-0">Atendemos desde compras unitarias hasta pedidos para abastecimiento comercial.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mp-contact-card h-100">
                    <i class="fas fa-truck"></i>
                    <div>
                        <h5>Respuesta rapida</h5>
                        <p class="mb-0">Coordinamos entregas y cotizaciones por canales directos para cerrar pedidos con agilidad.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
