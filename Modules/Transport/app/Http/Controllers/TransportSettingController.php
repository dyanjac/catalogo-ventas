<?php

declare(strict_types=1);

namespace Modules\Transport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Transport\Enums\TransportEnvironment;
use Modules\Transport\Models\TransportSetting;
use Modules\Transport\Services\TransportGuideService;

class TransportSettingController extends Controller
{
    public function edit(OrganizationContextService $context): View
    {
        return view('transport::settings.edit', ['setting' => $this->setting($context)]);
    }

    public function update(Request $request, OrganizationContextService $context): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'environment' => ['required', Rule::enum(TransportEnvironment::class)],
            'provider' => ['required', Rule::in(['simulation', 'greenter'])],
            'dispatch_mode' => ['required', Rule::in(['sync', 'queue'])],
            'queue_connection' => ['nullable', 'string', 'max:60'],
            'queue_name' => ['required', 'string', 'max:60'],
            'sender_series' => ['required', 'regex:/^T[A-Z0-9]{3}$/'],
            'carrier_series' => ['required', 'regex:/^V[A-Z0-9]{3}$/'],
            'allow_carrier_without_sender' => ['nullable', 'boolean'],
            'provider_credentials' => ['nullable', 'array:company_ruc,company_legal_name,company_commercial_name,company_ubigeo,company_department,company_province,company_district,company_address,sol_user,sol_password,api_client_id,api_client_secret,certificate_path'],
            'provider_credentials.company_ruc' => ['nullable', 'digits:11'],
            'provider_credentials.company_legal_name' => ['nullable', 'string', 'max:250'],
            'provider_credentials.company_commercial_name' => ['nullable', 'string', 'max:250'],
            'provider_credentials.company_ubigeo' => ['nullable', 'digits:6'],
            'provider_credentials.company_department' => ['nullable', 'string', 'max:100'],
            'provider_credentials.company_province' => ['nullable', 'string', 'max:100'],
            'provider_credentials.company_district' => ['nullable', 'string', 'max:100'],
            'provider_credentials.company_address' => ['nullable', 'string', 'max:500'],
            'provider_credentials.sol_user' => ['nullable', 'string', 'max:100'],
            'provider_credentials.sol_password' => ['nullable', 'string', 'max:200'],
            'provider_credentials.api_client_id' => ['nullable', 'string', 'max:200'],
            'provider_credentials.api_client_secret' => ['nullable', 'string', 'max:500'],
            'provider_credentials.certificate_path' => ['nullable', 'regex:/^[A-Za-z0-9._-]+\.pem$/', 'max:160'],
        ]);
        if (($data['environment'] === 'simulation') !== ($data['provider'] === 'simulation')) {
            return back()->withErrors(['provider' => 'Simulacion usa el proveedor simulado; produccion usa Greenter.'])->withInput();
        }
        $setting = $this->setting($context);
        $credentials = array_filter($data['provider_credentials'] ?? [], fn ($value): bool => is_string($value) ? trim($value) !== '' : $value !== null);
        $setting->fill([
            ...$data,
            'enabled' => $request->boolean('enabled'),
            'allow_carrier_without_sender' => $request->boolean('allow_carrier_without_sender'),
            'provider_credentials' => [...($setting->provider_credentials ?? []), ...$credentials],
            'credentials_hash' => null,
            'credentials_validated_at' => null,
        ])->save();

        return back()->with('success', 'Configuracion GRE actualizada. Debes revalidar credenciales antes de produccion.');
    }

    public function validateCredentials(OrganizationContextService $context, TransportGuideService $service): RedirectResponse
    {
        $service->validateCredentials($this->setting($context));

        return back()->with('success', 'Credenciales GRE validadas para la configuracion actual.');
    }

    private function setting(OrganizationContextService $context): TransportSetting
    {
        $organizationId = (int) $context->currentOrganizationId();
        abort_if($organizationId < 1, 422, 'Selecciona una organizacion activa.');

        return TransportSetting::query()->firstOrCreate(['organization_id' => $organizationId], [
            'enabled' => false, 'environment' => 'simulation', 'provider' => 'simulation', 'dispatch_mode' => 'queue',
            'queue_name' => 'transport', 'sender_series' => 'T001', 'carrier_series' => 'V001',
        ]);
    }
}
