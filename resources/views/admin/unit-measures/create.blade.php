@extends('layouts.admin')

@section('title', 'Nueva unidad')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Nueva Unidad" />

        <x-admin.form-card
            :action="route('admin.unit-measures.store')"
            submit-label="Guardar"
            :cancel-href="route('admin.unit-measures.index')"
        >
                @include('admin.unit-measures._form')
        </x-admin.form-card>
    </div>
</div>
@endsection

