@extends('layouts.admin')

@section('title', 'Preview plantilla XSLT')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="'Preview: ' . $template->name">
            <x-slot:actions>
                <a href="{{ route('admin.electronic-documents.templates.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <p class="mb-2"><strong>XML:</strong> {{ $xmlPath }}</p>
                <hr>
                <iframe srcdoc="{{ e($html) }}" style="width:100%;height:900px;border:1px solid #ddd;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

