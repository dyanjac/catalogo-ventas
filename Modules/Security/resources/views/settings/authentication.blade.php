@extends('layouts.admin')

@section('title', 'Seguridad')

@section('content')
    @livewire(\Modules\Security\Livewire\AuthenticationSettingsScreen::class)
@endsection
