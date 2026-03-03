<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommerceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CommerceSettingController extends Controller
{
    public function edit(): View
    {
        $setting = CommerceSetting::query()->firstOrCreate(
            ['id' => 1],
            ['company_name' => 'Mi Empresa', 'email' => '']
        );

        return view('admin.settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = CommerceSetting::query()->firstOrCreate(
            ['id' => 1],
            ['company_name' => 'Mi Empresa', 'email' => '']
        );

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_logo') && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo_file')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $data['logo_path'] = $request->file('logo_file')->store('settings', 'public');
        }

        unset($data['logo_file'], $data['remove_logo']);

        $setting->update($data);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Configuracion del comercio actualizada correctamente.');
    }
}
