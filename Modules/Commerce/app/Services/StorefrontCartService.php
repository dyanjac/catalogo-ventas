<?php

namespace Modules\Commerce\Services;

use App\Services\OrganizationContextService;

class StorefrontCartService
{
    public function __construct(private readonly OrganizationContextService $organizationContext) {}

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return session($this->sessionKey(), []);
    }

    /** @param array<string,array<string,mixed>> $cart */
    public function replace(array $cart): void
    {
        session([$this->sessionKey() => $cart]);
    }

    public function forget(): void
    {
        session()->forget($this->sessionKey());
    }

    public function sessionKey(): string
    {
        $organizationId = $this->organizationContext->publicStorefront()?->id;

        return $organizationId ? "ecommerce.cart.{$organizationId}" : 'cart';
    }
}
