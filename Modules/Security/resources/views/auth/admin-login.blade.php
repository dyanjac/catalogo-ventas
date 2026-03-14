@extends('layouts.auth')

@section('title', 'Acceso administrativo')

@section('content')
    @livewire(\Modules\Security\Livewire\AdminLoginScreen::class)
@endsection
