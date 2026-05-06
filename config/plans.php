<?php

return [
    'free' => [
        'max_monitors'               => 5,
        'min_interval_minutes'       => 5,
        'max_status_pages'           => 1,
        'intervals'                  => [5],
        'max_confirmation_threshold' => 1,
        'response_time_alerts'       => false,
        'max_ssl_monitors'           => 1,
        'max_keyword_monitors'       => 1,
        'allowed_check_types'        => ['http', 'ping'],
        'configurable_notifications' => false,
    ],
    'pro' => [
        'max_monitors'               => null,
        'min_interval_minutes'       => 1,
        'max_status_pages'           => null,
        'intervals'                  => [1, 2, 3, 5],
        'max_confirmation_threshold' => 3,
        'response_time_alerts'       => true,
        'max_ssl_monitors'           => null,
        'max_keyword_monitors'       => null,
        'allowed_check_types'        => ['http', 'ping', 'tcp'],
        'configurable_notifications' => true,
    ],
];
