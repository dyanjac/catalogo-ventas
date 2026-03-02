@extends('layouts.app')

@section('title', 'Crear producto')

@section('content')
<div class="container-fluid py-5 mt-5">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Nuevo Producto</h1>
            <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        @include('partials.flash')

        <form method="POST" action="{{ route('admin.products.store') }}" class="card border border-secondary rounded-3" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                @include('admin.products._form')
                @include('admin.products._image-manager')
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
