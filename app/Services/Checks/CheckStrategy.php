<?php

declare(strict_types=1);

namespace App\Services\Checks;

use App\Models\Monitor;

interface CheckStrategy
{
    public function run(Monitor $monitor): CheckExecutionResult;
}
