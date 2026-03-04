@extends('layouts.admin')

@section('title', 'Editar unidad')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Editar Unidad" />

        <x-admin.form-card
            :action="route('admin.unit-measures.update', $unitMeasure)"
            method="PUT"
            submit-label="Actualizar"
            :cancel-href="route('admin.unit-measures.index')"
        >
                @include('admin.unit-measures._form')
        </x-admin.form-card>
    </div>
</div>
@endsection

