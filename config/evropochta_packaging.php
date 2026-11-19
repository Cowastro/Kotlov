<?php

return [
    // Planning inner dimensions include a clearance allowance for padding.
    'box-20-15-10' => ['outer' => [20, 15, 10], 'fit' => [17, 12, 7], 'extra_kg' => 0.25],
    'box-24-24-20' => ['outer' => [24, 24, 20], 'fit' => [21, 21, 17], 'extra_kg' => 0.35],
    // Shipping carton for grouped orders, with allowance for the carton and filler.
    'box-50-30-30' => ['outer' => [50, 30, 30], 'fit' => [47, 27, 27], 'extra_kg' => 0.75, 'combined_only' => true],
];
