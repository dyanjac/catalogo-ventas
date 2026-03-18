<?php

namespace Modules\Security\Services;

use App\Models\User;
use Modules\Security\Models\SecurityBranch;

class SecurityBranchContextService
{
    public function currentBranchId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if ($user?->branch_id) {
            return (int) $user->branch_id;
        }

        $defaultBranchId = SecurityBranch::query()->where('is_default', true)->value('id');

        return $defaultBranchId ? (int) $defaultBranchId : null;
    }

    public function defaultBranchId(): ?int
    {
        $defaultBranchId = SecurityBranch::query()->where('is_default', true)->value('id');

        return $defaultBranchId ? (int) $defaultBranchId : null;
    }
}
