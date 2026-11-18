<?php

namespace App\Services\Rides;

final class RidePricing
{
    public const BILLING_MODEL = 'started_time_blocks';

    /** @return array<string, float|int|string> */
    public static function snapshot(): array
    {
        $blockMinutes = max(1, (int) config('pricing.rides.minutes', 20));
        $blockPrice = max(0.0, (float) config('pricing.rides.price_birr', 10));

        return [
            'billing_model' => self::BILLING_MODEL,
            'unlock_fee' => max(0.0, (float) config('pricing.rides.unlock_fee', 5)),
            'block_price' => $blockPrice,
            'block_minutes' => $blockMinutes,
            'per_minute_rate' => $blockPrice / $blockMinutes,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, float|int|string>
     */
    public static function calculate(int $minutes, ?array $snapshot = null): array
    {
        $tariff = ($snapshot['billing_model'] ?? null) === self::BILLING_MODEL
            ? array_merge(self::snapshot(), $snapshot)
            : self::snapshot();

        $blockMinutes = max(1, (int) $tariff['block_minutes']);
        $blockPrice = max(0.0, (float) $tariff['block_price']);
        $unlockFee = max(0.0, (float) $tariff['unlock_fee']);
        $billableMinutes = max(1, $minutes);
        $billedBlocks = intdiv($billableMinutes + $blockMinutes - 1, $blockMinutes);

        return [
            'billing_model' => self::BILLING_MODEL,
            'unlock_fee' => $unlockFee,
            'block_price' => $blockPrice,
            'block_minutes' => $blockMinutes,
            'billed_blocks' => $billedBlocks,
            'minutes' => $minutes,
            'per_minute_rate' => $blockPrice / $blockMinutes,
            'ride_charge' => round($blockPrice * $billedBlocks, 2),
            'total_amount' => round($unlockFee + ($blockPrice * $billedBlocks), 2),
        ];
    }
}
