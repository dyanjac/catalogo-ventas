<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** Exact base-10 arithmetic for persisted quantities and money. */
final class Decimal
{
    public static function normalize(int|float|string $value): string
    {
        $raw = is_float($value) ? sprintf('%.12F', $value) : trim((string) $value);

        if (! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException('Se esperaba un numero decimal finito.');
        }

        $negative = str_starts_with($raw, '-');
        $parts = explode('.', ltrim($raw, '+-'), 2);
        $whole = ltrim($parts[0], '0');
        $fraction = rtrim($parts[1] ?? '', '0');
        $normalized = ($whole === '' ? '0' : $whole).($fraction === '' ? '' : '.'.$fraction);

        return $negative && $normalized !== '0' ? '-'.$normalized : $normalized;
    }

    public static function assertScale(int|float|string $value, int $scale): string
    {
        $normalized = self::normalize($value);
        $fraction = explode('.', $normalized, 2)[1] ?? '';

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("El numero admite hasta {$scale} decimales.");
        }

        return $normalized;
    }

    public static function quantity(int|float|string $value): int|string
    {
        $number = self::assertScale($value, 4);

        return ! str_contains($number, '.') && (string) (int) $number === $number
            ? (int) $number
            : $number;
    }

    public static function absolute(int|float|string $value): string
    {
        return ltrim(self::normalize($value), '-');
    }

    public static function nonNegative(int|float|string $value): string
    {
        return self::compare($value, 0) > 0 ? self::normalize($value) : '0';
    }

    public static function unitPriceForInput(int|float|string $value): string
    {
        $normalized = self::assertScale($value, 6);
        $decimals = strlen(explode('.', $normalized, 2)[1] ?? '');

        return self::round($normalized, max(2, $decimals));
    }

    public static function add(int|float|string $left, int|float|string $right, int $scale = 12): string
    {
        return self::normalize(bcadd(self::normalize($left), self::normalize($right), $scale));
    }

    public static function sub(int|float|string $left, int|float|string $right, int $scale = 12): string
    {
        return self::normalize(bcsub(self::normalize($left), self::normalize($right), $scale));
    }

    public static function mul(int|float|string $left, int|float|string $right, int $scale = 12): string
    {
        return self::normalize(bcmul(self::normalize($left), self::normalize($right), $scale));
    }

    public static function div(int|float|string $left, int|float|string $right, int $scale = 12): string
    {
        if (self::compare($right, '0') === 0) {
            throw new InvalidArgumentException('No se puede dividir entre cero.');
        }

        return self::normalize(bcdiv(self::normalize($left), self::normalize($right), $scale));
    }

    public static function compare(int|float|string $left, int|float|string $right, int $scale = 12): int
    {
        return bccomp(self::normalize($left), self::normalize($right), $scale);
    }

    public static function round(int|float|string $value, int $scale, string $mode = 'half_up'): string
    {
        if ($scale < 0 || ! in_array($mode, ['half_up', 'half_even'], true)) {
            throw new InvalidArgumentException('Precision o metodo de redondeo invalido.');
        }

        $number = self::normalize($value);
        $absolute = ltrim($number, '-');
        $truncated = bcadd($absolute, '0', $scale);
        $precision = max(strlen(explode('.', $absolute, 2)[1] ?? ''), $scale + 1);
        $remainder = bcsub($absolute, $truncated, $precision);
        $half = '0.'.str_repeat('0', $scale).'5';
        $comparison = bccomp($remainder, $half, $precision);
        $lastDigit = (int) substr(str_replace('.', '', $truncated), -1);

        if ($comparison > 0 || ($comparison === 0 && ($mode === 'half_up' || $lastDigit % 2 !== 0))) {
            $step = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $truncated = bcadd($truncated, $step, $scale);
        }

        $rounded = $number[0] === '-' && bccomp($truncated, '0', $scale) !== 0 ? '-'.$truncated : $truncated;

        return $rounded;
    }
}
