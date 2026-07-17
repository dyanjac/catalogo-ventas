<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Subscriptions\Services\AccrualScheduleAllocator;
use PHPUnit\Framework\TestCase;

class AccrualScheduleAllocatorTest extends TestCase
{
    public function test_it_uses_half_open_monthly_slices_and_preserves_every_minor_unit(): void
    {
        $slices = (new AccrualScheduleAllocator)->allocate(
            CarbonImmutable::parse('2026-01-31', 'UTC'),
            CarbonImmutable::parse('2026-04-30', 'UTC'),
            100,
        );

        $this->assertCount(4, $slices);
        $this->assertSame('2026-01-31', $slices[0]['starts_on']->toDateString());
        $this->assertSame('2026-02-01', $slices[0]['ends_on']->toDateString());
        $this->assertSame('2026-04-30', $slices[3]['ends_on']->toDateString());
        $this->assertSame(100, array_sum(array_column($slices, 'amount_minor')));
    }

    public function test_leap_year_allocation_is_deterministic(): void
    {
        $allocator = new AccrualScheduleAllocator;
        $first = $allocator->allocate(CarbonImmutable::parse('2024-02-01'), CarbonImmutable::parse('2024-05-01'), 10_001);
        $replay = $allocator->allocate(CarbonImmutable::parse('2024-02-01'), CarbonImmutable::parse('2024-05-01'), 10_001);

        $this->assertSame(array_column($first, 'amount_minor'), array_column($replay, 'amount_minor'));
        $this->assertSame(10_001, array_sum(array_column($first, 'amount_minor')));
    }
}
