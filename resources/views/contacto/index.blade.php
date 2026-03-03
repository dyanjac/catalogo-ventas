@extends('layouts.app')

@section('title', 'Contacto')

@section('content')
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-5">
            <div>
                <span class="mp-kicker">Contacto comercial</span>
                <h1>Estamos listos para atender pedidos, stock y cotizaciones</h1>
                <p>Habla con el equipo para coordinar abastecimiento, entrega y compras mayoristas de productos de consumo masivo.</p>
            </div>
            <a href="https://wa.me/51915923681" target="_blank" class="btn btn-primary rounded-pill px-4">
                <i class="fab fa-whatsapp me-2"></i>WhatsApp directo
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="mp-detail-gallery p-0 overflow-hidden">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d124838.40749526641!2d-77.14006052421982!3d-12.098440466840426!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c69943de55ed%3A0x563b8f31ab745bcc!2sMercado%20Productores!5e0!3m2!1ses!2spe!4v1753655372504!5m2!1ses!2spe"
                        class="rounded w-100"
                        style="height: 100%; min-height: 520px;"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="mp-contact-stack">
                    <div class="mp-contact-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h5>Direccion</h5>
                            <p class="mb-0">Psj. Señor de los Milagros 01</p>
                        </div>
                    </div>
                    <div class="mp-contact-card">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h5>Correo comercial</h5>
                            <p class="mb-0">maestropanadero.maestro@gmail.com</p>
                        </div>
                    </div>
                    <div class="mp-contact-card">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <h5>Celular</h5>
                            <p class="mb-0">+51 915 923 681</p>
                        </div>
                    </div>
                    <div class="mp-contact-card">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h5>Horario</h5>
                            <p class="mb-0">Lunes a Sabado, 8:00 am a 7:00 pm</p>
                        </div>
                    </div>
                    <div class="mp-cart-summary">
                        <span class="mp-kicker">Atencion inmediata</span>
                        <h4 class="mb-3">Canales directos de venta</h4>
                        <p class="mb-4">Solicita precios por volumen, confirma disponibilidad y coordina entregas con el equipo comercial.</p>
                        <div class="d-grid gap-3">
                            <a href="https://wa.me/51915923681" target="_blank" class="btn btn-primary btn-lg rounded-pill">
                                <i class="fab fa-whatsapp me-2"></i>Contactar por WhatsApp
                            </a>
                            <a href="mailto:maestropanadero.maestro@gmail.com" class="btn btn-light border btn-lg rounded-pill">
                                <i class="fas fa-envelope me-2"></i>Enviar correo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
