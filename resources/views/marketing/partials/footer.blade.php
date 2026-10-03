<footer class="marketing-footer">
    <div class="marketing-container marketing-footer__main">
        <div class="marketing-footer__identity">
            <a class="marketing-brand" href="{{ route('home') }}" aria-label="{{ config('marketing.brand_name') }}: inicio">
                <x-marketing.brand-mark id="footer" />
                <span>{{ config('marketing.brand_name') }}</span>
            </a>
            <p>Una plataforma para conectar cada paso de tu operación comercial.</p>
        </div>
        <nav class="marketing-footer__nav" aria-label="Enlaces de la plataforma">
            <span>Explora</span>
            <a href="{{ route('home') }}#plataforma">Plataforma</a>
            <a href="{{ route('home') }}#capacidades">Capacidades</a>
        </nav>
        <nav class="marketing-footer__nav" aria-label="Enlaces de acceso">
            <span>Comienza</span>
            <a href="{{ route('saas.register.create') }}">Crear organización</a>
            <a href="{{ route('admin.login') }}">Acceso al ERP</a>
        </nav>
    </div>
    <div class="marketing-container marketing-footer__bottom">
        <span>© {{ now()->year }} {{ config('marketing.brand_name') }}</span>
        <span>Ventas, inventario y facturación en un solo lugar.</span>
    </div>
</footer>
