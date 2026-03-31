<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationContextService
{
    public function current(): ?Organization
    {
        if (! Schema::hasTable('organizations')) {
            return null;
        }

        $user = auth()->user();

        if ($user instanceof User && $user->organization_id) {
            return Organization::query()->find($user->organization_id);
        }

        return $this->explicit() ?? Organization::query()
            ->where('is_default', true)
            ->first()
            ?? Organization::query()->orderBy('id')->first();
    }

    public function explicit(): ?Organization
    {
        if (! Schema::hasTable('organizations')) {
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

                return $organization;
            }
        }

        if ($request?->hasSession()) {
            $sessionSlug = $request->session()->get('organization_context_slug');

            if (is_string($sessionSlug) && trim($sessionSlug) !== '') {
                $organization = Organization::query()->where('slug', $sessionSlug)->first();

                if ($organization) {
                    return $organization;
                }

                $request->session()->forget('organization_context_slug');
            }
        }

        return null;
    }

    public function rememberExplicit(?string $slug): ?Organization
    {
        $request = request();

        if (! $request?->hasSession()) {
            return null;
        }

        if (! is_string($slug) || trim($slug) === '') {
            $request->session()->forget('organization_context_slug');

            return null;
        }

        $organization = Organization::query()->where('slug', Str::slug($slug))->first();

        if (! $organization) {
            $request->session()->forget('organization_context_slug');

            return null;
        }

        $request->session()->put('organization_context_slug', $organization->slug);

        return $organization;
    }

    public function clearExplicit(): void
    {
        if (request()?->hasSession()) {
            request()->session()->forget('organization_context_slug');
        }
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

    /**
     * @return array{organization_id:int|null,organization_name:string|null,environment:string,is_demo:bool}
     */
    public function forView(): array
    {
        $organization = $this->current();

        return [
            'organization_id' => $organization?->id,
            'organization_name' => $organization?->name,
            'environment' => $this->currentEnvironment(),
            'is_demo' => $this->isDemo(),
        ];
    }
}
