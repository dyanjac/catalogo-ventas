<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UnitMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitMeasureController extends Controller
{
    public function index(): View
    {
        $unitMeasures = UnitMeasure::withCount('products')->orderBy('name')->paginate(15);

        return view('admin.unit-measures.index', compact('unitMeasures'));
    }

    public function create(): View
    {
        return view('admin.unit-measures.create', ['unitMeasure' => new UnitMeasure()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:unit_measures,name'],
        ]);

        UnitMeasure::create($data);

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad creada correctamente.');
    }

    public function edit(UnitMeasure $unitMeasure): View
    {
        return view('admin.unit-measures.edit', compact('unitMeasure'));
    }

    public function update(Request $request, UnitMeasure $unitMeasure): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:unit_measures,name,' . $unitMeasure->id],
        ]);

        $unitMeasure->update($data);

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad actualizada correctamente.');
    }

    public function destroy(UnitMeasure $unitMeasure): RedirectResponse
    {
        abort_if($unitMeasure->products()->exists(), 422, 'No se puede eliminar una unidad con productos asociados.');

        $unitMeasure->delete();

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad eliminada correctamente.');
    }
}
