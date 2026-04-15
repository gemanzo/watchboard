<?php

namespace App\Events;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Foundation\Events\Dispatchable;

class MonitorStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Monitor     $monitor,
        public readonly string      $oldStatus,
        public readonly string      $newStatus,
        public readonly CheckResult $checkResult,
        /** Downtime in seconds (populated only on down → up transitions). */
        public readonly ?int        $downtimeSeconds = null,
    ) {}
}
