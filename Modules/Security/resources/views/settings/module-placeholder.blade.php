@extends('layouts.admin')

@section('page_title', $module->name)

@section('content')
    <x-admin.page-header
        eyebrow="Modulo futuro"
        :title="$module->name"
        :description="$module->description ?: 'Modulo reservado dentro de la arquitectura modular y pendiente de implementacion.'"
    />

    <section class="security-card">
        <div class="security-card__header">
            <div>
                <div class="security-card__eyebrow">Estado</div>
                <h3 class="security-card__title">Modulo en construccion</h3>
            </div>
            <flux:badge color="amber">{{ strtoupper($module->status) }}</flux:badge>
        </div>

        <p class="auth-screen__copy">
            Este modulo ya forma parte de la matriz de acceso del sistema, pero su funcionalidad operativa aun no ha sido implementada.
        </p>
    </section>
@endsection
