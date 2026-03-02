<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validateWithBag('login', [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Credenciales inválidas.'], 'login')
                ->withInput($request->only('email'))
                ->with('openAuthModal', 'login');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'))
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function register(Request $request)
    {
        $data = $request->validateWithBag('register', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', Rule::in(['dni', 'ruc', 'ce', 'pasaporte'])],
            'document_number' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))
            ->with('success', 'Cuenta creada e inicio de sesión exitoso.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Sesión cerrada.');
    }
}
