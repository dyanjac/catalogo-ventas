@extends('layouts.admin')

@section('title', 'Editar unidad')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Editar Unidad</h1>
            <a href="{{ route('admin.unit-measures.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        <form method="POST" action="{{ route('admin.unit-measures.update', $unitMeasure) }}" class="card border border-secondary rounded-3">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('admin.unit-measures._form')
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection

