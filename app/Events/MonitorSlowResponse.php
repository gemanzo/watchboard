<?php

namespace App\Events;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Foundation\Events\Dispatchable;

class MonitorSlowResponse
{
    use Dispatchable;

    public function __construct(
        public readonly Monitor     $monitor,
        public readonly CheckResult $checkResult,
        /** The configured threshold that was exceeded, in milliseconds. */
        public readonly int         $thresholdMs,
    ) {}
}
