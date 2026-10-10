<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalPolicyTest extends TestCase
{
    public function test_half_up_and_half_even_match_the_agreed_tie_cases(): void
    {
        foreach ([
            ['2.25', '2.3', '2.2'],
            ['2.35', '2.4', '2.4'],
            ['2.45', '2.5', '2.4'],
            ['2.55', '2.6', '2.6'],
        ] as [$value, $halfUp, $halfEven]) {
            $this->assertSame($halfUp, Decimal::round($value, 1, 'half_up'));
            $this->assertSame($halfEven, Decimal::round($value, 1, 'half_even'));
        }

        $this->assertSame('2.3', Decimal::round('2.250001', 1, 'half_even'));
        $this->assertSame('-2.3', Decimal::round('-2.25', 1, 'half_up'));
        $this->assertSame('-2.2', Decimal::round('-2.25', 1, 'half_even'));
    }

    public function test_four_decimal_quantities_and_six_decimal_prices_are_exact(): void
    {
        $this->assertSame('0.001', Decimal::add('0.0005', '0.0005', 4));
        $this->assertSame('0.000000061728', Decimal::mul('0.0005', '0.000123456', 12));
        $this->assertSame('0.000123', Decimal::round('0.000123456', 6));
    }
}
