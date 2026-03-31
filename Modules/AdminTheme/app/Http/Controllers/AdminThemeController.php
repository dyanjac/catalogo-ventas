<?php

namespace Modules\AdminTheme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdminTheme\Services\AdminThemePaletteService;

class AdminThemeController extends Controller
{
    public function __construct(
        private readonly AdminThemePaletteService $paletteService,
        private readonly OrganizationContextService $organizationContext
    ) {
    }

    public function edit(): View
    {
        $organization = $this->organizationContext->current();

        return view('admintheme::edit', [
            'palette' => $this->paletteService->getPalette(),
            'organization' => $organization,
            'isSuspended' => $organization?->isSuspended() ?? false,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($this->organizationContext->isSuspended()) {
            return redirect()
                ->route('admin.theme.edit')
                ->with('error', 'La organización actual está suspendida. La paleta admin quedó en modo solo lectura.');
        }

        $keys = array_keys(config('admintheme.defaults', []));
        $rules = [];

        foreach ($keys as $key) {
            $rules[$key] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        $data = $request->validate($rules);
        $this->paletteService->updatePalette($data);

        return redirect()
            ->route('admin.theme.edit')
            ->with('success', 'Paleta del panel actualizada correctamente para la organizacion actual.');
    }

    public function reset(): RedirectResponse
    {
        if ($this->organizationContext->isSuspended()) {
            return redirect()
                ->route('admin.theme.edit')
                ->with('error', 'La organización actual está suspendida. No es posible restablecer la paleta mientras permanezca suspendida.');
        }

        $this->paletteService->resetPalette();

        return redirect()
            ->route('admin.theme.edit')
            ->with('success', 'La paleta del panel fue restablecida a los colores base de la organizacion actual.');
    }
}
