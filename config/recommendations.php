<?php

return [
    // Q/F/P/M/I weights are decimal strings because recommendation arithmetic uses BCMath.
    'weights' => [
        'quantity_coverage' => '60',
        'full' => '20',
        'partial' => '-10',
        'missing' => '-10',
        'incompatible' => '-10',
    ],
    'minimum_score' => '0',
    'maximum_score' => '80',

    // Pagination occurs after every recipe has been scored and globally sorted.
    'per_page' => 12,
];
