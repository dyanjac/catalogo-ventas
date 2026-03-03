@extends('layouts.app')

@section('title', 'Nueva unidad')

@section('content')
<div class="container-fluid py-5 mt-5">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Nueva Unidad</h1>
            <a href="{{ route('admin.unit-measures.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        @include('partials.flash')

        <form method="POST" action="{{ route('admin.unit-measures.store') }}" class="card border border-secondary rounded-3">
            @csrf
            <div class="card-body">
                @include('admin.unit-measures._form')
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
