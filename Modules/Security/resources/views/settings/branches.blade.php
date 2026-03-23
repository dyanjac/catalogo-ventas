@extends('layouts.admin')

@section('page_title', 'Sucursales')

@section('content')
    @livewire(\Modules\Security\Livewire\BranchManagementScreen::class)
@endsection
