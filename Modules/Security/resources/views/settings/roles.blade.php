@extends('layouts.admin')

@section('title', 'Roles y permisos')

@section('content')
    @livewire(\Modules\Security\Livewire\RolePermissionMatrixScreen::class)
@endsection
