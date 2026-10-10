<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Decimal;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Keeps integer reads compatible while preserving fractional stock exactly. */
final class Quantity implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int|string|null
    {
        if ($value === null) {
            return null;
        }

        return Decimal::quantity($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : bcadd(Decimal::assertScale($value, 4), '0', 4);
    }
}
