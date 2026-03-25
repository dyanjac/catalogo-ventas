<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

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

        return Organization::query()
            ->where('is_default', true)
            ->first()
            ?? Organization::query()->orderBy('id')->first();
    }

    public function currentOrganizationId(): ?int
    {
        return $this->current()?->id;
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
