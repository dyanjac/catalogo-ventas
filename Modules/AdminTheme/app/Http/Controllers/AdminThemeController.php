<?php

namespace Modules\AdminTheme\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdminTheme\Services\AdminThemePaletteService;

class AdminThemeController extends Controller
{
    public function __construct(private readonly AdminThemePaletteService $paletteService)
    {
    }

    public function edit(): View
    {
        return view('admintheme::edit', [
            'palette' => $this->paletteService->getPalette(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = array_keys(config('admintheme.defaults', []));
        $rules = [];

        foreach ($keys as $key) {
            $rules[$key] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        $data = $request->validate($rules);
        $this->paletteService->updatePalette($data);

        return redirect()
            ->route('admin.theme.edit')
            ->with('success', 'Paleta del panel actualizada correctamente.');
    }
}
