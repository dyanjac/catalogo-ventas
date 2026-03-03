@extends('layouts.admin')

@section('title', 'Editar producto')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Editar Producto</h1>
            <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="card border border-secondary rounded-3" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.products._form')
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Actualizar</button>
            </div>
        </form>

        <div class="mt-4">
            @include('admin.products._image-manager')
        </div>
    </div>
</div>
@endsection

