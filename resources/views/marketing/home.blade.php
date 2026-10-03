@extends('layouts.marketing')

@section('title', config('marketing.brand_name').' | Ventas, inventario y facturación')

@section('content')
    <section class="marketing-hero" aria-labelledby="marketing-hero-title">
        <div class="marketing-container marketing-hero__grid">
            <div>
                <p class="marketing-eyebrow">ERP para empresas que crecen</p>
                <h1 id="marketing-hero-title">Conecta tus ventas con toda tu operación.</h1>
                <p class="marketing-lead">
                    Administra pedidos, inventario, clientes y facturación electrónica desde un solo lugar.
                    Tu equipo trabaja con la misma información en cada etapa de la venta.
                </p>
                <div class="marketing-actions">
                    <a class="marketing-button" href="{{ route('saas.register.create') }}">Crear organización de prueba</a>
                    <a class="marketing-button marketing-button--outline" href="{{ route('home') }}#capacidades">Explorar la plataforma</a>
                </div>
            </div>

            <div class="marketing-preview" aria-label="Vista conceptual de la plataforma ERP">
                <div class="marketing-preview__top"><span></span><span></span><span></span><strong>Panel de operaciones</strong></div>
                <div class="marketing-preview__body">
                    <div class="marketing-preview__sidebar" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <div class="marketing-preview__content">
                        <p class="marketing-preview__label">Un flujo conectado</p>
                        <h2>De la venta al control</h2>
                        <div class="marketing-preview__flow">
                            <span>Ventas</span><span>Inventario</span><span>Facturación</span>
                        </div>
                        <div class="marketing-preview__rows" aria-hidden="true"><i></i><i></i><i></i></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="plataforma" class="marketing-section marketing-section--soft">
        <div class="marketing-container marketing-section__intro">
            <p class="marketing-eyebrow">Una plataforma integrada</p>
            <h2>Más claridad para cada decisión comercial.</h2>
            <p>Desde el primer pedido hasta el registro contable, la información acompaña a los equipos que hacen crecer tu negocio.</p>
        </div>
    </section>

    <section id="capacidades" class="marketing-section">
        <div class="marketing-container">
            <div class="marketing-section__intro">
                <p class="marketing-eyebrow">Capacidades principales</p>
                <h2>Todo lo que necesitas para vender y operar.</h2>
            </div>
            <div class="marketing-cards">
                <article class="marketing-card"><span>01</span><h3>Ventas y clientes</h3><p>Gestiona pedidos, punto de venta y relaciones comerciales en un mismo flujo.</p></article>
                <article class="marketing-card"><span>02</span><h3>Inventario y almacenes</h3><p>Consulta existencias y acompaña reservas, movimientos y despachos.</p></article>
                <article class="marketing-card"><span>03</span><h3>Facturación electrónica</h3><p>Emite documentos electrónicos desde las operaciones de tu empresa.</p></article>
                <article class="marketing-card"><span>04</span><h3>Ecommerce por empresa</h3><p>Publica una tienda propia conectada al catálogo de tu organización.</p></article>
            </div>
        </div>
    </section>

    <section class="marketing-cta">
        <div class="marketing-container marketing-cta__inner">
            <div><p class="marketing-eyebrow">Empieza con tu organización</p><h2>Tu operación comercial, en un solo sistema.</h2></div>
            <a class="marketing-button marketing-button--light" href="{{ route('saas.register.create') }}">Crear organización de prueba</a>
        </div>
    </section>
@endsection
