@extends('layouts.admin')

@section('title', 'Nueva plantilla XSLT')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Nueva plantilla XSLT" />

        <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <form action="{{ route('admin.electronic-documents.templates.store') }}" method="POST">
                    @php($template = null)
                    @php($method = 'POST')
                    @include('electronicdocuments::templates._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

