@extends('layouts.app-home')

@section('title', 'Iniciar sesion')

@section('content')
    <section class="py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row g-4 align-items-stretch">
                        <div class="col-lg-6">
                            <div class="h-100 rounded-4 border bg-white p-4 shadow-sm">
                                <div class="text-uppercase small fw-bold text-primary mb-2">Acceso cliente</div>
                                <h1 class="display-6 fw-bold mb-3">Inicia sesion en tu cuenta ecommerce</h1>
                                <p class="text-muted mb-4">
                                    Este acceso corresponde al canal publico del ecommerce. El panel administrativo ahora
                                    tiene su propio acceso independiente en <strong>/admin/login</strong>.
                                </p>

                                <div class="rounded-4 bg-light border p-4">
                                    <div class="fw-semibold mb-3">Canales de autenticacion</div>
                                    <div class="d-grid gap-2 text-muted small">
                                        <div>1. Cliente ecommerce: /login</div>
                                        <div>2. Panel admin: /admin/login</div>
                                        <div>3. Seguridad empresarial: modulo Security</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="h-100 rounded-4 border bg-white p-4 shadow-sm">
                                <h2 class="h3 fw-bold mb-4">Iniciar sesion</h2>

                                <form method="POST" action="{{ route('login.attempt') }}" class="row g-3">
                                    @csrf

                                    <div class="col-12">
                                        <label class="form-label">Correo</label>
                                        <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg" required autofocus>
                                        @error('email', 'login')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Contraseña</label>
                                        <input type="password" name="password" class="form-control form-control-lg" required>
                                        @error('password', 'login')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>

                                    <div class="col-12 form-check">
                                        <input type="checkbox" class="form-check-input" name="remember" id="rememberLoginPage" value="1">
                                        <label class="form-check-label" for="rememberLoginPage">Recordarme</label>
                                    </div>

                                    <div class="col-12 d-grid">
                                        <button class="btn btn-primary btn-lg">Ingresar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
