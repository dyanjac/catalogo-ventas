@extends('layouts.admin')

@section('title', 'Editar categoria')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Editar Categoria" />

        <x-admin.form-card
            :action="route('admin.categories.update', $category)"
            method="PUT"
            submit-label="Actualizar"
            :cancel-href="route('admin.categories.index')"
        >
                @include('admin.categories._form')
        </x-admin.form-card>
    </div>
</div>
@endsection

