@extends('layouts.marketing')

@section('title', 'Acceso al ERP | '.config('marketing.brand_name'))
@section('description', 'Acceso administrativo para organizaciones registradas en '.config('marketing.brand_name').'.')
@section('canonical', route('admin.login'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @livewireStyles
@endpush

@push('scripts')
    @livewireScripts
@endpush

@section('content')
    @livewire(\Modules\Security\Livewire\AdminLoginScreen::class)
@endsection
