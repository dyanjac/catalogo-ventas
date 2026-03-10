@extends('layouts.admin')

@section('title', 'Paleta Admin')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Paleta de colores AdminLTE">
            <x-slot:actions>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <p class="text-muted mb-4">Configura la paleta usada globalmente en el panel administrativo para todos los módulos.</p>

                <form method="POST" action="{{ route('admin.theme.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        @php
                            $labels = [
                                'sidebar_bg' => 'Sidebar color inicio',
                                'sidebar_gradient_to' => 'Sidebar color fin',
                                'sidebar_text' => 'Sidebar texto',
                                'topbar_bg' => 'Topbar fondo',
                                'topbar_text' => 'Topbar texto',
                                'primary_button' => 'Botón primario',
                                'primary_button_hover' => 'Botón primario hover',
                                'active_link_bg' => 'Link activo fondo',
                                'active_link_text' => 'Link activo texto',
                                'card_border' => 'Borde de tarjetas',
                                'focus_ring' => 'Color de foco',
                            ];
                        @endphp

                        @foreach($labels as $key => $label)
                            <div class="col-md-4">
                                <label class="form-label">{{ $label }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        type="color"
                                        class="form-control form-control-color p-1"
                                        value="{{ old($key, $palette[$key] ?? '#000000') }}"
                                        onchange="document.getElementById('{{ $key }}').value = this.value.toUpperCase()"
                                    >
                                    <input
                                        type="text"
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        class="form-control"
                                        value="{{ old($key, $palette[$key] ?? '#000000') }}"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        required
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Guardar paleta</button>
                        <a href="{{ route('admin.theme.edit') }}" class="btn btn-light border rounded-pill px-4">Recargar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
