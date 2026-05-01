<?php

return [
    'free' => [
        'max_monitors'              => 5,
        'min_interval_minutes'      => 5,
        'max_status_pages'          => 1,
        'intervals'                 => [5],
        'max_confirmation_threshold' => 1,
    ],
    'pro' => [
        'max_monitors'              => null,
        'min_interval_minutes'      => 1,
        'max_status_pages'          => null,
        'intervals'                 => [1, 2, 3, 5],
        'max_confirmation_threshold' => 3,
    ],
];
