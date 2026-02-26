<!-- resources/views/categories/show.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="text-primary">{{ $category->name }}</h1>
    <div class="row">
        @foreach ($products as $product)
        <div class="col-md-4">
            <div class="card">
                <img src="{{ asset('storage/'.$product->image) }}" class="card-img-top" alt="{{ $product->name }}">
                <div class="card-body">
                    <h5 class="card-title">{{ $product->name }}</h5>
                    <p class="card-text">{{ $product->description }}</p>
                    <a href="{{ route('products.show', $product->slug) }}" class="btn btn-primary">Ver detalle</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
