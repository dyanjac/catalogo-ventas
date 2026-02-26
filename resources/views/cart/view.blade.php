<!-- resources/views/cart/view.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="text-primary">Mi Carrito</h1>
    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <!-- Aquí irán los productos del carrito -->
            @foreach ($cartItems as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->price }}</td>
                <td>{{ $item->total }}</td>
                <td>
                    <button class="btn btn-danger">Eliminar</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <a href="#" class="btn btn-success">Proceder al pago</a>
</div>
@endsection
