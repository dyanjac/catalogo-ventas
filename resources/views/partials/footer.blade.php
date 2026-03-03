<footer class="container-fluid footer pt-5 mt-5 mp-footer">
    <div class="container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <div class="mp-footer-brand">
                    <img src="{{ asset('img/logo-V&V.png') }}" alt="Maestro Panadero" class="mp-footer-logo mb-3">
                    <h4 class="text-white mb-2">Maestro Panadero</h4>
                    <p class="text-white-50 mb-3">Venta de insumos de consumo masivo para panaderias, bodegas y negocios que necesitan reposicion constante.</p>
                    <div class="mp-footer-social">
                        <a href="#" class="btn btn-outline-secondary rounded-circle"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-outline-secondary rounded-circle"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="btn btn-outline-secondary rounded-circle"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-2">
                <h5 class="text-white mb-3">Navegación</h5>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('home') }}" class="btn-link">Inicio</a>
                    <a href="{{ route('catalog.index') }}" class="btn-link">Catálogo</a>
                    <a href="{{ route('contacto.index') }}" class="btn-link">Contacto</a>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <h5 class="text-white mb-3">Categorias clave</h5>
                <div class="d-flex flex-column gap-2">
                    <span class="btn-link">Harinas</span>
                    <span class="btn-link">Azucar</span>
                    <span class="btn-link">Manteca</span>
                    <span class="btn-link">Abarrotes</span>
                </div>
            </div>

            <div class="col-lg-3">
                <h5 class="text-white mb-3">Recibe promociones</h5>
                <p class="text-white-50">Enterate de precios especiales y productos de alta rotacion.</p>
                <div class="position-relative mx-auto">
                    <input class="form-control border-0 w-100 py-3 px-4 rounded-pill" type="email" placeholder="Tu correo">
                    <button type="button" class="btn btn-primary border-0 py-3 px-4 position-absolute rounded-pill text-white" style="top: 0; right: 0;">Suscribirme</button>
                </div>
            </div>
        </div>

        <div class="mp-footer-bottom mt-5 pt-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <p class="text-white-50 mb-0">© {{ now()->year }} Maestro Panadero. Todos los derechos reservados.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <span class="text-white-50 me-3">Psj. Señor de los Milagros 01</span>
                    <span class="text-white-50">maestropanadero.maestro@gmail.com</span>
                </div>
            </div>
        </div>
    </div>
</footer>
