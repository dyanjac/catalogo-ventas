@extends('layouts.marketing')

@section('title', 'Acceso administrativo')

@push('styles')
    @livewireStyles
@endpush

@push('scripts')
    @livewireScripts
@endpush

@section('content')
    @livewire(\Modules\Security\Livewire\AdminLoginScreen::class)
@endsection
