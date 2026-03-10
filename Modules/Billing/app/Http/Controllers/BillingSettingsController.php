<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\BillingProviderResolver;

class BillingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('billing::settings.edit', [
            'setting' => $this->setting(),
            'providers' => config('billing.providers', []),
            'environments' => config('billing.environments', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'country' => ['required', 'in:PE'],
            'provider' => ['required', 'in:greenter,nubefact,tefacturo,efact'],
            'environment' => ['required', 'in:sandbox,production'],
            'dispatch_mode' => ['required', 'in:sync,queue'],
            'queue_connection' => ['nullable', 'string', 'max:40'],
            'queue_name' => ['nullable', 'string', 'max:80'],
            'invoice_series' => ['nullable', 'string', 'max:10'],
            'receipt_series' => ['nullable', 'string', 'max:10'],
            'credit_note_series' => ['nullable', 'string', 'max:10'],
            'debit_note_series' => ['nullable', 'string', 'max:10'],
            'provider_credentials' => ['nullable', 'array'],
        ]);

        $setting = $this->setting();
        $updateData = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'country' => $data['country'],
            'provider' => $data['provider'],
            'environment' => $data['environment'],
            'invoice_series' => $this->normalizeSeries($data['invoice_series'] ?? null),
            'receipt_series' => $this->normalizeSeries($data['receipt_series'] ?? null),
            'credit_note_series' => $this->normalizeSeries($data['credit_note_series'] ?? null),
            'debit_note_series' => $this->normalizeSeries($data['debit_note_series'] ?? null),
            'provider_credentials' => $this->normalizeCredentials($data['provider_credentials'] ?? []),
        ];

        if (Schema::hasColumn('billing_settings', 'dispatch_mode')) {
            $updateData['dispatch_mode'] = $data['dispatch_mode'];
        }
        if (Schema::hasColumn('billing_settings', 'queue_connection')) {
            $updateData['queue_connection'] = $this->normalizeNullableString($data['queue_connection'] ?? null);
        }
        if (Schema::hasColumn('billing_settings', 'queue_name')) {
            $updateData['queue_name'] = $this->normalizeNullableString($data['queue_name'] ?? null);
        }

        $setting->update($updateData);

        return back()->with('success', 'Configuración de facturación electrónica actualizada.');
    }

    public function testConnection(BillingProviderResolver $resolver): RedirectResponse
    {
        $setting = $this->setting();

        if (! $setting->enabled) {
            return back()->withErrors(['enabled' => 'Activa la facturación electrónica antes de probar conexión.']);
        }

        $provider = $resolver->resolveFromSetting($setting);
        $result = $provider->testConnection($setting);

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['provider' => $result['message'] ?? 'No se pudo validar el proveedor.']);
        }

        return back()->with('success', $result['message'] ?? 'Conexión validada.');
    }

    private function setting(): BillingSetting
    {
        $defaults = [
            'enabled' => false,
            'country' => 'PE',
            'provider' => 'greenter',
            'environment' => 'sandbox',
            'provider_credentials' => [],
        ];

        if (Schema::hasColumn('billing_settings', 'dispatch_mode')) {
            $defaults['dispatch_mode'] = 'sync';
        }
        if (Schema::hasColumn('billing_settings', 'queue_connection')) {
            $defaults['queue_connection'] = null;
        }
        if (Schema::hasColumn('billing_settings', 'queue_name')) {
            $defaults['queue_name'] = null;
        }

        return BillingSetting::query()->firstOrCreate([], $defaults);
    }

    private function normalizeSeries(?string $series): ?string
    {
        $value = strtoupper(trim((string) $series));

        return $value !== '' ? $value : null;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string,mixed> $credentials
     * @return array<string,mixed>
     */
    private function normalizeCredentials(array $credentials): array
    {
        $normalized = [];

        foreach (['greenter', 'nubefact', 'tefacturo', 'efact'] as $provider) {
            $bucket = $credentials[$provider] ?? [];
            if (! is_array($bucket)) {
                $normalized[$provider] = [];
                continue;
            }

            $normalized[$provider] = collect($bucket)
                ->map(function ($value) {
                    return is_string($value) ? trim($value) : $value;
                })
                ->filter(function ($value) {
                    return ! (is_string($value) && $value === '');
                })
                ->toArray();
        }

        return $normalized;
    }
}
