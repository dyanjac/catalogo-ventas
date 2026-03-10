@extends('layouts.admin')

@section('title', 'Configuración contable')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Configuración contable" />

        <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.accounting.settings.update') }}" class="row g-4">
                    @csrf
                    @method('PUT')

                    <div class="col-md-3">
                        <label class="form-label">Ejercicio fiscal</label>
                        <input type="number" min="2000" max="2100" name="fiscal_year" class="form-control" value="{{ old('fiscal_year', $settings->fiscal_year) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mes inicio fiscal</label>
                        <input type="number" min="1" max="12" name="fiscal_year_start_month" class="form-control" value="{{ old('fiscal_year_start_month', $settings->fiscal_year_start_month) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda por defecto</label>
                        <input type="text" name="default_currency" class="form-control text-uppercase" maxlength="3" value="{{ old('default_currency', $settings->default_currency) }}" required>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check me-4">
                            <input type="hidden" name="period_closure_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="period_closure_enabled" id="period_closure_enabled" value="1" @checked(old('period_closure_enabled', $settings->period_closure_enabled))>
                            <label class="form-check-label" for="period_closure_enabled">Habilitar cierre por periodo</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="auto_post_entries" value="0">
                            <input class="form-check-input" type="checkbox" name="auto_post_entries" id="auto_post_entries" value="1" @checked(old('auto_post_entries', $settings->auto_post_entries))>
                            <label class="form-check-label" for="auto_post_entries">Publicar asientos automáticamente al generar comprobantes</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Guardar configuración</button>
                        <a href="{{ route('admin.accounting.entries.index') }}" class="btn btn-light border rounded-pill px-4">Ver asientos</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
