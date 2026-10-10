<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class RoundingPolicy
{
    public function mode(int $organizationId, ?int $branchId = null): string
    {
        if ($branchId !== null) {
            $branchMode = DB::table('security_branches')
                ->where('organization_id', $organizationId)
                ->where('id', $branchId)
                ->value('rounding_mode');

            if (in_array($branchMode, ['half_up', 'half_even'], true)) {
                return $branchMode;
            }
        }

        $globalMode = DB::table('commerce_settings')
            ->where('organization_id', $organizationId)
            ->value('rounding_mode');

        return in_array($globalMode, ['half_up', 'half_even'], true) ? $globalMode : 'half_up';
    }

    public function money(int|float|string $value, int $organizationId, ?int $branchId = null): string
    {
        return Decimal::round($value, 2, $this->mode($organizationId, $branchId));
    }

    public function lineSubtotal(int|float|string $quantity, int|float|string $unitPrice, int $organizationId, ?int $branchId = null): string
    {
        return $this->money(Decimal::mul($quantity, $unitPrice, 10), $organizationId, $branchId);
    }

    public function lineTax(int|float|string $roundedBase, int|float|string $rate, int $organizationId, ?int $branchId = null): string
    {
        return $this->money(Decimal::mul($roundedBase, $rate, 12), $organizationId, $branchId);
    }
}
