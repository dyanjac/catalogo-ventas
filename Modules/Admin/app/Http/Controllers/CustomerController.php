<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('admin.customers.index');
    }

    public function show(User $customer): View
    {
        $customer->load(['orders' => fn ($query) => $query->latest()->take(10)]);

        return view('admin.customers.show', compact('customer'));
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $customer->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', 'in:dni,ruc,ce,pasaporte'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'role' => ['required', 'in:customer,super_admin'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $customer->update($data);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Cliente actualizado correctamente.');
    }
}
