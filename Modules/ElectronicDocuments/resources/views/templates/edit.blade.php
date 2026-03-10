@extends('layouts.admin')

@section('title', 'Editar plantilla XSLT')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="'Editar plantilla #' . $template->id" />

        <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <form action="{{ route('admin.electronic-documents.templates.update', $template) }}" method="POST">
                    @php($method = 'PUT')
                    @include('electronicdocuments::templates._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

