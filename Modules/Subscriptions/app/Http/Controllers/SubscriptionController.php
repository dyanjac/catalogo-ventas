<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationContextService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductType;
use Modules\Security\Models\SecurityBranch;
use Modules\Subscriptions\Models\CustomerSubscription;
use Modules\Subscriptions\Models\SubscriptionServicePeriod;
use Modules\Subscriptions\Services\SubscriptionBillingLinkService;
use Modules\Subscriptions\Services\SubscriptionLifecycleService;

class SubscriptionController extends Controller
{
    public function index(OrganizationContextService $context): View
    {
        $subscriptions = CustomerSubscription::query()->where('organization_id', $context->currentOrganizationId())
            ->with(['customer', 'product'])->latest('id')->paginate(20);

        return view('subscriptions::index', compact('subscriptions'));
    }

    public function create(OrganizationContextService $context): View
    {
        $organizationId = (int) $context->currentOrganizationId();

        return view('subscriptions::create', [
            'idempotencyKey' => (string) Str::uuid(),
            'customers' => User::query()->where('organization_id', $organizationId)->orderBy('name')->get(),
            'products' => Product::query()->where('organization_id', $organizationId)->where('product_type', ProductType::Subscription->value)->where('is_active', true)->orderBy('name')->get(),
            'branches' => SecurityBranch::query()->where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get(),
            'billingDocuments' => $this->availableBillingDocuments($organizationId),
        ]);
    }

    public function store(Request $request, OrganizationContextService $context, SubscriptionLifecycleService $service, SubscriptionBillingLinkService $billingLinks): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:160'], 'code' => ['nullable', 'string', 'max:40'],
            'customer_id' => ['required', 'integer'], 'product_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'],
            'currency' => ['required', 'string', 'size:3'], 'billing_cycle_months' => ['required', 'integer', 'in:1,3,12'],
            'recurring_subtotal' => ['required', 'numeric', 'gt:0'], 'recurring_tax' => ['nullable', 'numeric', 'min:0'],
            'service_starts_on' => ['required', 'date'],
            'billing_document_id' => ['required', 'integer'],
        ]);
        $subscription = DB::transaction(function () use ($data, $request, $context, $service, $billingLinks): CustomerSubscription {
            $subscription = $service->activate([
                ...$data, 'organization_id' => (int) $context->currentOrganizationId(),
                'created_by' => $request->user()?->id,
                'recurring_subtotal_minor' => (int) round(((float) $data['recurring_subtotal']) * 100),
                'recurring_tax_minor' => (int) round(((float) ($data['recurring_tax'] ?? 0)) * 100),
            ]);
            $period = $subscription->periods()->where('sequence', 1)->firstOrFail();
            $billingLinks->attach($period, BillingDocument::query()->findOrFail((int) $data['billing_document_id']));

            return $subscription;
        }, 3);

        return redirect()->route('admin.subscriptions.show', $subscription)->with('success', 'SuscripciÃ³n activada y calendario generado.');
    }

    public function show(CustomerSubscription $subscription): View
    {
        return view('subscriptions::show', [
            'subscription' => $subscription->load(['customer', 'product', 'periods.accruals.economicEvent']),
            'billingDocuments' => $this->availableBillingDocuments((int) $subscription->organization_id),
        ]);
    }

    public function renew(CustomerSubscription $subscription, SubscriptionLifecycleService $service): RedirectResponse
    {
        $service->renew($subscription, "subscription:{$subscription->id}:renewal:".($subscription->renewal_count + 1));

        return back()->with('success', 'Nuevo periodo generado sin alterar el historial.');
    }

    public function cancel(Request $request, CustomerSubscription $subscription, SubscriptionLifecycleService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'immediately' => ['nullable', 'boolean']]);
        $service->cancel($subscription, (bool) ($data['immediately'] ?? false), $data['reason']);

        return back()->with('success', 'CancelaciÃ³n registrada.');
    }

    public function adjust(Request $request, CustomerSubscription $subscription, SubscriptionLifecycleService $service): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'not_in:0'], 'due_on' => ['required', 'date'], 'reason' => ['required', 'string', 'max:1000']]);
        $service->adjust($subscription, (int) round(((float) $data['amount']) * 100), CarbonImmutable::parse($data['due_on'], 'UTC'), $data['reason'], (string) Str::uuid());

        return back()->with('success', 'Ajuste compensatorio programado.');
    }

    public function attachBilling(Request $request, CustomerSubscription $subscription, SubscriptionServicePeriod $period, SubscriptionBillingLinkService $service): RedirectResponse
    {
        abort_unless((int) $period->subscription_id === (int) $subscription->id, 404);
        $data = $request->validate(['billing_document_id' => ['required', 'integer']]);
        $service->attach($period, BillingDocument::query()->findOrFail((int) $data['billing_document_id']));

        return back()->with('success', 'Comprobante vinculado y evento de ingreso diferido registrado.');
    }

    private function availableBillingDocuments(int $organizationId)
    {
        return BillingDocument::query()->where('organization_id', $organizationId)->where('status', 'issued')
            ->whereHas('order.items.product', fn ($product) => $product->where('product_type', ProductType::Subscription->value))
            ->latest('id')->limit(100)->get();
    }
}
