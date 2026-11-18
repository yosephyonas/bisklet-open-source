<?php

namespace Tests\Unit;

use App\Services\Rides\RidePricing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RidePricingTest extends TestCase
{
    #[DataProvider('fareCases')]
    public function test_it_charges_each_started_twenty_minute_block(
        int $minutes,
        int $blocks,
        float $total,
    ): void {
        $pricing = RidePricing::calculate($minutes);

        $this->assertSame($blocks, $pricing['billed_blocks']);
        $this->assertSame($total, $pricing['total_amount']);
    }

    /** @return array<string, array{int, int, float}> */
    public static function fareCases(): array
    {
        return [
            'first minute' => [1, 1, 15.0],
            'exactly 20 minutes' => [20, 1, 15.0],
            'minute 21' => [21, 2, 25.0],
            'exactly 40 minutes' => [40, 2, 25.0],
            'minute 41' => [41, 3, 35.0],
        ];
    }
}
