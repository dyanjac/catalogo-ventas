<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use Carbon\CarbonImmutable;
use DomainException;

final class AccrualScheduleAllocator
{
    /** @return array<int,array{starts_on:CarbonImmutable,ends_on:CarbonImmutable,due_on:CarbonImmutable,amount_minor:int}> */
    public function allocate(CarbonImmutable $start, CarbonImmutable $end, int $amountMinor): array
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new DomainException('El fin del servicio debe ser posterior al inicio.');
        }
        if ($amountMinor < 0) {
            throw new DomainException('El importe del periodo no puede ser negativo.');
        }

        $slices = [];
        $cursor = $start;
        while ($cursor->lessThan($end)) {
            $nextMonth = $cursor->startOfMonth()->addMonth();
            $sliceEnd = $nextMonth->lessThan($end) ? $nextMonth : $end;
            $slices[] = ['starts_on' => $cursor, 'ends_on' => $sliceEnd];
            $cursor = $sliceEnd;
        }

        $days = array_map(fn (array $slice): int => (int) $slice['starts_on']->diffInDays($slice['ends_on']), $slices);
        $totalDays = array_sum($days);
        $allocated = 0;

        return array_map(function (array $slice, int $index) use ($amountMinor, $days, $totalDays, &$allocated, $slices): array {
            $isLast = $index === array_key_last($slices);
            $value = $isLast ? $amountMinor - $allocated : intdiv($amountMinor * $days[$index], $totalDays);
            $allocated += $value;

            return [
                ...$slice,
                'due_on' => $slice['ends_on']->subDay(),
                'amount_minor' => $value,
            ];
        }, $slices, array_keys($slices));
    }
}
