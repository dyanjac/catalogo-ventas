@extends('layouts.admin')

@section('title', 'Accesos de usuarios')

@section('content')
    @livewire(\Modules\Security\Livewire\UserRoleAssignmentsScreen::class)
@endsection
