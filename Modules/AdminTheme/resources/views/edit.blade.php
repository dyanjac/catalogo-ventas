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
                <p class="text-muted mb-2">
                    Configura la paleta usada por el panel administrativo de la organizacion actual.
                </p>
                <p class="mb-4 d-flex gap-2 flex-wrap">
                    <span class="badge badge-light px-3 py-2">Organizacion: {{ $organization?->name ?? 'Sin contexto organizacional' }}</span>
                    @if($isSuspended)
                        <span class="badge badge-danger px-3 py-2">Tenant suspendido · solo lectura</span>
                    @endif
                </p>

                @if($isSuspended)
                    <div class="alert alert-warning rounded-4 mb-4">
                        La organización actual está suspendida. Puedes revisar la paleta aplicada, pero no modificarla ni restablecerla hasta reactivar el tenant.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.theme.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        @php
                            $labels = [
                                'sidebar_bg' => 'Sidebar color inicio',
                                'sidebar_gradient_to' => 'Sidebar color fin',
                                'sidebar_text' => 'Sidebar texto',
                                'sidebar_group_text' => 'Sidebar texto grupos',
                                'sidebar_group_bg' => 'Sidebar fondo grupos',
                                'topbar_bg' => 'Topbar fondo',
                                'topbar_text' => 'Topbar texto',
                                'primary_button' => 'Boton primario',
                                'primary_button_hover' => 'Boton primario hover',
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
                                        @disabled($isSuspended)
                                    >
                                    <input
                                        type="text"
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        class="form-control"
                                        value="{{ old($key, $palette[$key] ?? '#000000') }}"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        @disabled($isSuspended)
                                        required
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary rounded-pill px-4" @disabled($isSuspended)>Guardar paleta</button>
                        <a href="{{ route('admin.theme.edit') }}" class="btn btn-light border rounded-pill px-4">Recargar</a>
                    </div>
                </form>

                <hr class="my-4">

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">Restablecer colores base</h5>
                        <p class="text-muted mb-0">Elimina la paleta personalizada de esta organizacion y vuelve a los colores por defecto del sistema.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.theme.reset') }}" onsubmit="return confirm('Se restablecera la paleta de la organizacion actual. ?Deseas continuar?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-primary rounded-pill px-4" @disabled($isSuspended)>Restablecer paleta por defecto</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
