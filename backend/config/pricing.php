<?php

$blockPrice = (float) env('RIDE_PRICE_BIRR', 10);
$blockMinutes = max(1, (int) env('RIDE_PRICE_MINUTES', 20));

return [
    'rides' => [
        // A started ride pays the unlock fee plus one full block. Each newly
        // started block adds another block charge (1-20, 21-40 minutes, etc.).
        'price_birr' => $blockPrice,
        'minutes' => $blockMinutes,
        'unlock_fee' => (float) env('RIDE_UNLOCK_FEE_BIRR', 5),
        // Kept for compatibility with older clients and database columns.
        'per_minute_rate' => $blockPrice / $blockMinutes,
    ],
];
