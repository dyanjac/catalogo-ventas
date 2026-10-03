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

            <figure class="marketing-product">
                <div class="marketing-preview" aria-hidden="true">
                    <div class="marketing-preview__top">
                        <span class="marketing-preview__mini-mark">M</span>
                        <strong>Panel de operaciones</strong>
                        <span class="marketing-preview__top-pill">Vista general</span>
                    </div>
                    <div class="marketing-preview__body">
                        <div class="marketing-preview__sidebar"><i></i><i></i><i></i><i></i></div>
                        <div class="marketing-preview__content">
                            <div class="marketing-preview__heading">
                                <div><p>Resumen comercial</p><strong>Todo conectado</strong></div>
                                <span>Hoy</span>
                            </div>
                            <div class="marketing-preview__tiles">
                                <div><span>Pedidos</span><strong>En seguimiento</strong><i></i></div>
                                <div><span>Inventario</span><strong>Disponible</strong><i></i></div>
                                <div><span>Documentos</span><strong>Organizados</strong><i></i></div>
                            </div>
                            <div class="marketing-preview__flow">
                                <span>Venta</span><b></b><span>Almacén</span><b></b><span>Facturación</span>
                            </div>
                        </div>
                    </div>
                </div>
                <figcaption>Vista conceptual de un flujo comercial conectado</figcaption>
            </figure>
        </div>
    </section>

    <section id="plataforma" class="marketing-section marketing-section--soft">
        <div class="marketing-container marketing-section__intro">
            <p class="marketing-eyebrow">Una plataforma integrada</p>
            <h2>Más claridad para cada decisión comercial.</h2>
            <p>Desde el primer pedido hasta el registro contable, la información acompaña a los equipos que hacen crecer tu negocio.</p>
            <div class="marketing-connection" aria-label="Flujo entre equipos">
                <span>Ventas</span><i aria-hidden="true"></i><span>Almacén</span><i aria-hidden="true"></i><span>Facturación</span>
            </div>
        </div>
    </section>

    <section id="capacidades" class="marketing-section">
        <div class="marketing-container">
            <div class="marketing-section__intro">
                <p class="marketing-eyebrow">Capacidades principales</p>
                <h2>Todo lo que necesitas para vender y operar.</h2>
            </div>
            <div class="marketing-cards">
                <article class="marketing-card"><span class="marketing-card__number">01</span><x-marketing.feature-icon name="sales" /><h3>Ventas y clientes</h3><p>Gestiona pedidos, punto de venta y relaciones comerciales en un mismo flujo.</p></article>
                <article class="marketing-card"><span class="marketing-card__number">02</span><x-marketing.feature-icon name="inventory" /><h3>Inventario y almacenes</h3><p>Consulta existencias y acompaña reservas, movimientos y despachos.</p></article>
                <article class="marketing-card"><span class="marketing-card__number">03</span><x-marketing.feature-icon name="billing" /><h3>Facturación electrónica</h3><p>Emite documentos electrónicos desde las operaciones de tu empresa.</p></article>
                <article class="marketing-card"><span class="marketing-card__number">04</span><x-marketing.feature-icon name="commerce" /><h3>Ecommerce por empresa</h3><p>Publica una tienda propia conectada al catálogo de tu organización.</p></article>
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
