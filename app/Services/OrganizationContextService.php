<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationContextService
{
    public const PUBLIC_STOREFRONT_ATTRIBUTE = 'organization_context.public_storefront';

    private bool $organizationsTableResolved = false;

    private bool $organizationsTableExists = false;

    private bool $currentResolved = false;

    private ?Organization $currentOrganization = null;

    private bool $explicitResolved = false;

    private ?Organization $explicitOrganization = null;

    private ?array $viewContext = null;

    private bool $contextIdentityResolved = false;

    private ?Request $contextRequest = null;

    private int|string|null $contextUserId = null;

    private int|string|null $contextStorefrontId = null;

    public function current(): ?Organization
    {
        $this->synchronizeContextIdentity();

        $publicStorefront = $this->publicStorefront();

        if ($publicStorefront) {
            if (! $this->currentResolved || $this->currentOrganization?->id !== $publicStorefront->id) {
                $this->setCurrent($publicStorefront);
            }

            return $this->currentOrganization;
        }

        if ($this->currentResolved) {
            return $this->currentOrganization;
        }

        if (! $this->organizationsTableExists()) {
            return $this->setCurrent(null);
        }

        $user = auth()->user();

        if ($user instanceof User && $user->organization_id) {
            return $this->setCurrent(
                $user->relationLoaded('organization')
                    ? $user->organization
                    : Organization::query()->find($user->organization_id)
            );
        }

        return $this->setCurrent(
            $this->explicit() ?? Organization::query()
                ->where('is_default', true)
                ->first()
                ?? Organization::query()->orderBy('id')->first()
        );
    }

    public function explicit(): ?Organization
    {
        $this->synchronizeContextIdentity();

        if ($this->explicitResolved) {
            return $this->explicitOrganization;
        }

        if (! $this->organizationsTableExists()) {
            $this->explicitResolved = true;

            return null;
        }

        $request = request();
        $requestedSlug = is_string($request?->query('org')) ? trim((string) $request->query('org')) : '';

        if ($requestedSlug !== '') {
            $organization = Organization::query()->where('slug', Str::slug($requestedSlug))->first();

            if ($organization) {
                if ($request?->hasSession()) {
                    $request->session()->put('organization_context_slug', $organization->slug);
                }

                return $this->setExplicit($organization);
            }
        }

        if ($request?->hasSession()) {
            $sessionSlug = $request->session()->get('organization_context_slug');

            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                $organization = Organization::query()->where('slug', $sessionSlug)->first();

                if ($organization) {
                    return $this->setExplicit($organization);
                }

                $request->session()->forget('organization_context_slug');
            }
        }

        return $this->setExplicit(null);
    }

    public function rememberExplicit(?string $slug): ?Organization
    {
        $this->synchronizeContextIdentity();

        $request = request();

        if (! $request?->hasSession()) {
            return null;
        }

        if (! is_string($slug) || trim($slug) === '') {
            $request->session()->forget('organization_context_slug');

            $this->setExplicit(null);
            $this->forgetCurrent();

            return null;
        }

        $organization = Organization::query()->where('slug', Str::slug($slug))->first();

        if (! $organization) {
            $request->session()->forget('organization_context_slug');

            $this->setExplicit(null);
            $this->forgetCurrent();

            return null;
        }

        $request->session()->put('organization_context_slug', $organization->slug);
        $this->setExplicit($organization);
        $this->forgetCurrent();

        return $organization;
    }

    public function clearExplicit(): void
    {
        $this->synchronizeContextIdentity();

        if (request()?->hasSession()) {
            request()->session()->forget('organization_context_slug');
        }

        $this->setExplicit(null);
        $this->forgetCurrent();
    }

    public function currentOrganizationId(): ?int
    {
        return $this->current()?->id;
    }

    public function currentStatus(): ?string
    {
        return $this->current()?->status;
    }

    public function isSuspended(): bool
    {
        return $this->current()?->isSuspended() ?? false;
    }

    public function currentEnvironment(): string
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            return 'demo';
        }

        return $this->current()?->environment ?? 'production';
    }

    public function isDemo(): bool
    {
        return $this->currentEnvironment() === 'demo';
    }

    public function publicStorefront(): ?Organization
    {
        $this->synchronizeContextIdentity();

        $organization = request()?->attributes->get(self::PUBLIC_STOREFRONT_ATTRIBUTE);

        return $organization instanceof Organization ? $organization : null;
    }

    /**
     * @return array{organization_id:int|null,organization_name:string|null,environment:string,is_demo:bool}
     */
    public function forView(): array
    {
        $this->synchronizeContextIdentity();

        if ($this->viewContext !== null) {
            return $this->viewContext;
        }

        $organization = $this->current();
        $environment = app()->environment(['local', 'development', 'testing'])
            ? 'demo'
            : ($organization?->environment ?? 'production');

        return $this->viewContext = [
            'organization_id' => $organization?->id,
            'organization_name' => $organization?->name,
            'environment' => $environment,
            'is_demo' => $environment === 'demo',
        ];
    }

    private function organizationsTableExists(): bool
    {
        if (! $this->organizationsTableResolved) {
            $this->organizationsTableExists = Schema::hasTable('organizations');
            $this->organizationsTableResolved = true;
        }

        return $this->organizationsTableExists;
    }

    private function setCurrent(?Organization $organization): ?Organization
    {
        $this->currentOrganization = $organization;
        $this->currentResolved = true;
        $this->viewContext = null;

        return $organization;
    }

    private function forgetCurrent(): void
    {
        $this->currentOrganization = null;
        $this->currentResolved = false;
        $this->viewContext = null;
    }

    private function setExplicit(?Organization $organization): ?Organization
    {
        $this->explicitOrganization = $organization;
        $this->explicitResolved = true;

        return $organization;
    }

    private function synchronizeContextIdentity(): void
    {
        $request = request();
        $userId = auth()->id();
        $storefront = $request->attributes->get(self::PUBLIC_STOREFRONT_ATTRIBUTE);
        $storefrontId = $storefront instanceof Organization ? $storefront->getKey() : null;

        if (! $this->contextIdentityResolved) {
            $this->contextIdentityResolved = true;
            $this->contextRequest = $request;
            $this->contextUserId = $userId;
            $this->contextStorefrontId = $storefrontId;

            return;
        }

        $requestChanged = $this->contextRequest !== $request;
        $identityChanged = $this->contextUserId !== $userId
            || $this->contextStorefrontId !== $storefrontId;

        if (! $requestChanged && ! $identityChanged) {
            return;
        }

        $this->contextRequest = $request;
        $this->contextUserId = $userId;
        $this->contextStorefrontId = $storefrontId;
        $this->forgetCurrent();

        if ($requestChanged) {
            $this->explicitOrganization = null;
            $this->explicitResolved = false;
        }
    }
}
