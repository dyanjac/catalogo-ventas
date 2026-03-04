@extends('layouts.admin')

@section('title', 'Crear producto')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Nuevo Producto" />

        <x-admin.form-card
            :action="route('admin.products.store')"
            enctype="multipart/form-data"
            submit-label="Guardar"
            :cancel-href="route('admin.products.index')"
        >
                @include('admin.products._form')
                @include('admin.products._image-manager')
        </x-admin.form-card>
    </div>
</div>
@endsection

