@extends('layouts.app')

@section('title', 'Contacto')

@section('content')
<div class="container-fluid contact py-5">
    <div class="container py-5">
        <div class="p-5 bg-light rounded-4 border border-secondary-subtle">
            <div class="row g-4">
                <div class="col-12">
                    <div class="text-center mx-auto" style="max-width: 720px;">
                        <h5 class="text-secondary text-uppercase">Contacto</h5>
                        <h1 class="text-primary mb-3">Estamos listos para atender tus pedidos y consultas</h1>
                        <p class="mb-0">Si necesitas cotizar productos, coordinar una entrega o recibir atención comercial, puedes escribirnos o visitarnos.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="h-100 rounded overflow-hidden border">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d124838.40749526641!2d-77.14006052421982!3d-12.098440466840426!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c69943de55ed%3A0x563b8f31ab745bcc!2sMercado%20Productores!5e0!3m2!1ses!2spe!4v1753655372504!5m2!1ses!2spe"
                            class="rounded w-100"
                            style="height: 420px;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex p-4 rounded mb-4 bg-white border">
                        <i class="fas fa-map-marker-alt fa-2x text-primary me-4"></i>
                        <div>
                            <h4>Dirección</h4>
                            <p class="mb-0">psj. Señor de los Milagros 01</p>
                        </div>
                    </div>
                    <div class="d-flex p-4 rounded mb-4 bg-white border">
                        <i class="fas fa-envelope fa-2x text-primary me-4"></i>
                        <div>
                            <h4>Correo</h4>
                            <p class="mb-0">maestropanadero.maestro@gmail.com</p>
                        </div>
                    </div>
                    <div class="d-flex p-4 rounded mb-4 bg-white border">
                        <i class="fa fa-phone-alt fa-2x text-primary me-4"></i>
                        <div>
                            <h4>Celular</h4>
                            <p class="mb-0">+51 915 923 681</p>
                        </div>
                    </div>
                    <div class="p-4 rounded bg-white border">
                        <h4 class="text-primary">Atención comercial</h4>
                        <p class="mb-3">Escríbenos por WhatsApp o por correo para cotizaciones, pedidos o consultas sobre stock.</p>
                        <a href="https://wa.me/51915923681" target="_blank" class="btn btn-primary rounded-pill px-4">
                            <i class="fab fa-whatsapp me-2"></i> Contactar por WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
