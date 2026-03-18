@extends('layouts.admin')

@section('title', 'Auditoria de seguridad')

@section('content')
    @livewire(\Modules\Security\Livewire\AuditLogScreen::class)
@endsection
