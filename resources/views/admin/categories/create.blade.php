@extends('layouts.admin')

@section('title', 'Nueva categoria')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Nueva Categoria" />

        <x-admin.form-card
            :action="route('admin.categories.store')"
            submit-label="Guardar"
            :cancel-href="route('admin.categories.index')"
        >
                @include('admin.categories._form')
        </x-admin.form-card>
    </div>
</div>
@endsection

