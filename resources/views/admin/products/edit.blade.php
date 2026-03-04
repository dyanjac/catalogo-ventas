@extends('layouts.admin')

@section('title', 'Editar producto')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Editar Producto" />

        <x-admin.form-card
            :action="route('admin.products.update', $product)"
            method="PUT"
            enctype="multipart/form-data"
            submit-label="Actualizar"
            :cancel-href="route('admin.products.index')"
        >
                @include('admin.products._form')
        </x-admin.form-card>

        <div class="mt-4">
            @include('admin.products._image-manager')
        </div>
    </div>
</div>
@endsection

