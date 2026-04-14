<?php

namespace App\Jobs;

use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PerformCheck implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public function __construct(public readonly Monitor $monitor)
    {
        $this->onQueue('checks');
    }

    /**
     * Prevent duplicate jobs for the same monitor.
     * The uniqueness lock is held until the job starts processing.
     */
    public function uniqueId(): int
    {
        return $this->monitor->id;
    }

    /**
     * HTTP check logic — implemented in Sprint 3 (US-3.x).
     */
    public function handle(): void
    {
        // TODO: perform HTTP request and store CheckResult
    }
}
