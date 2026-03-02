@extends('layouts.app')

@section('title', 'Nosotros')

@section('content')
<div class="container-fluid py-5">
    <div class="container py-5">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="position-relative">
                    <img src="{{ asset('img/hero-img-1.png') }}" class="img-fluid rounded-4 shadow-sm" alt="Maestro Panadero">
                    <div class="position-absolute bg-primary text-white rounded-pill px-4 py-2" style="bottom: 20px; left: 20px;">
                        Insumos para panificación
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <h5 class="text-secondary text-uppercase mb-3">Nosotros</h5>
                <h1 class="display-5 text-primary mb-4">Distribuimos insumos confiables para panaderías y negocios de alimentos</h1>
                <p class="mb-3">Maestro Panadero nace para atender con rapidez a clientes que necesitan harinas, mantecas, azúcares, esencias y otros insumos clave para su operación diaria.</p>
                <p class="mb-4">Nuestro enfoque combina stock disponible, atención directa y precios competitivos para compras minoristas y mayoristas.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-4 h-100 bg-light">
                            <h5 class="text-primary">Misión</h5>
                            <p class="mb-0">Abastecer a nuestros clientes con productos de calidad, atención rápida y soluciones comerciales prácticas.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-4 h-100 bg-light">
                            <h5 class="text-primary">Visión</h5>
                            <p class="mb-0">Ser un referente local en distribución de insumos para panificación y repostería.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <div class="col-md-4">
                <div class="card border border-secondary h-100">
                    <div class="card-body">
                        <h5 class="text-primary">Atención personalizada</h5>
                        <p class="mb-0">Te ayudamos a elegir productos según el volumen, tipo de negocio y frecuencia de compra.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border border-secondary h-100">
                    <div class="card-body">
                        <h5 class="text-primary">Venta minorista y mayorista</h5>
                        <p class="mb-0">Trabajamos pedidos unitarios y también abastecimiento comercial para clientes frecuentes.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border border-secondary h-100">
                    <div class="card-body">
                        <h5 class="text-primary">Cobertura local</h5>
                        <p class="mb-0">Coordinamos entregas y atención por canales directos para responder con rapidez.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
