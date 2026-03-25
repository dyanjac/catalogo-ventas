<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UnitMeasure;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitMeasureController extends Controller
{
    public function index(): View
    {
        return view('admin.unit-measures.index');
    }

    public function create(): View
    {
        return view('admin.unit-measures.create', ['unitMeasure' => new UnitMeasure()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('unit_measures', 'name')->where('organization_id', $organizationId)],
        ]);

        UnitMeasure::create($data);

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad creada correctamente.');
    }

    public function edit(UnitMeasure $unitMeasure): View
    {
        abort_unless($this->belongsToCurrentOrganization($unitMeasure), 404);

        return view('admin.unit-measures.edit', compact('unitMeasure'));
    }

    public function update(Request $request, UnitMeasure $unitMeasure): RedirectResponse
    {
        abort_unless($this->belongsToCurrentOrganization($unitMeasure), 404);

        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('unit_measures', 'name')->where('organization_id', $organizationId)->ignore($unitMeasure->id)],
        ]);

        $unitMeasure->update($data);

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad actualizada correctamente.');
    }

    public function destroy(UnitMeasure $unitMeasure): RedirectResponse
    {
        abort_unless($this->belongsToCurrentOrganization($unitMeasure), 404);
        abort_if($unitMeasure->products()->exists(), 422, 'No se puede eliminar una unidad con productos asociados.');

        $unitMeasure->delete();

        return redirect()->route('admin.unit-measures.index')->with('success', 'Unidad eliminada correctamente.');
    }

    private function belongsToCurrentOrganization(UnitMeasure $unitMeasure): bool
    {
        return (int) $unitMeasure->organization_id === (int) app(OrganizationContextService::class)->currentOrganizationId();
    }
}
