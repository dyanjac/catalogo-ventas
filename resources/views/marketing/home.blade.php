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
                <p class="marketing-hero__note">El registro inicial crea una organización en entorno DEMO.</p>
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
                <article class="marketing-card"><span class="marketing-card__number">04</span><x-marketing.feature-icon name="commerce" /><h3>Ecommerce por empresa</h3><p>Ofrece un catálogo público propio cuando tu organización tenga habilitado el canal ecommerce.</p></article>
            </div>
        </div>
    </section>

    <section id="como-empezar" class="marketing-section marketing-section--soft" aria-labelledby="marketing-steps-title">
        <div class="marketing-container">
            <div class="marketing-section__intro">
                <p class="marketing-eyebrow">Cómo empezar</p>
                <h2 id="marketing-steps-title">De tu organización al trabajo diario.</h2>
                <p>Un punto de partida claro para tu equipo, con la operación comercial reunida en el mismo sistema.</p>
            </div>
            <ol class="marketing-steps">
                <li>
                    <span class="marketing-steps__number">01</span>
                    <h3>Crea tu organización DEMO</h3>
                    <p>Registra los datos de tu empresa, su sucursal principal y la persona que administrará el espacio.</p>
                </li>
                <li>
                    <span class="marketing-steps__number">02</span>
                    <h3>Accede al panel administrativo</h3>
                    <p>Recibe las credenciales iniciales al terminar el registro e ingresa para configurar tu operación.</p>
                </li>
                <li>
                    <span class="marketing-steps__number">03</span>
                    <h3>Conecta tus procesos</h3>
                    <p>Trabaja con ventas, inventario y documentos desde los módulos disponibles para tu organización.</p>
                </li>
            </ol>
            <div class="marketing-steps__action">
                <p>¿Ya tienes una organización? <a href="{{ route('admin.login') }}">Ingresa al ERP</a>.</p>
                <a class="marketing-button" href="{{ route('saas.register.create') }}">Crear mi organización DEMO</a>
            </div>
        </div>
    </section>

    <section id="ecommerce" class="marketing-section" aria-labelledby="marketing-commerce-title">
        <div class="marketing-container marketing-commerce">
            <div>
                <p class="marketing-eyebrow">ERP + ecommerce</p>
                <h2 id="marketing-commerce-title">La plataforma y tu tienda tienen espacios propios.</h2>
                <p>Esta portada presenta el ERP. Cada empresa puede tener su escaparate público separado, con identidad y catálogo propios, si el canal ecommerce está habilitado para su organización.</p>
                <a class="marketing-text-link" href="{{ route('saas.register.create') }}">Comenzar con una organización <span aria-hidden="true">→</span></a>
            </div>
            <div class="marketing-commerce__routes" aria-label="Separación de la plataforma y las tiendas">
                <div>
                    <span class="marketing-commerce__tag">Plataforma</span>
                    <strong>El ERP para tu equipo</strong>
                    <code>/</code>
                    <p>Conoce las capacidades y crea tu organización.</p>
                </div>
                <div>
                    <span class="marketing-commerce__tag marketing-commerce__tag--cyan">Tu comercio</span>
                    <strong>Una tienda para tus clientes</strong>
                    <code>/ecommerce/{nombre-comercio}</code>
                    <p>Su acceso público depende de la capacidad ecommerce de la organización.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="preguntas" class="marketing-section marketing-section--soft" aria-labelledby="marketing-faq-title">
        <div class="marketing-container marketing-faq">
            <div class="marketing-section__intro">
                <p class="marketing-eyebrow">Preguntas frecuentes</p>
                <h2 id="marketing-faq-title">Lo esencial antes de empezar.</h2>
            </div>
            <div class="marketing-faq__list">
                <details>
                    <summary>¿Necesito iniciar sesión para crear una organización?</summary>
                    <p>No. Puedes completar el registro público y crear tu organización inicial en entorno DEMO.</p>
                </details>
                <details>
                    <summary>¿Qué recibo al terminar el registro?</summary>
                    <p>Se crea la sucursal principal y un administrador inicial. La contraseña temporal se muestra al finalizar para que puedas ingresar al panel.</p>
                </details>
                <details>
                    <summary>¿Mi tienda aparecerá en esta portada?</summary>
                    <p>No. La portada del ERP y las tiendas de las empresas son independientes. La tienda usa la ruta /ecommerce/{nombre-comercio} cuando el canal está habilitado.</p>
                </details>
                <details>
                    <summary>¿El registro DEMO activa automáticamente el entorno de producción?</summary>
                    <p>No. El paso a producción es un proceso posterior y controlado.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="marketing-cta">
        <div class="marketing-container marketing-cta__inner">
            <div><p class="marketing-eyebrow">Empieza con tu organización</p><h2>Tu operación comercial, en un solo sistema.</h2><p class="marketing-cta__copy">Crea tu espacio DEMO y conoce cómo se conectan tus equipos.</p></div>
            <a class="marketing-button marketing-button--light" href="{{ route('saas.register.create') }}">Crear organización DEMO</a>
        </div>
    </section>
@endsection
