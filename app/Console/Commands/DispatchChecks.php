<?php

namespace App\Console\Commands;

use App\Jobs\PerformCheck;
use App\Models\Monitor;
use Illuminate\Console\Command;

class DispatchChecks extends Command
{
    protected $signature = 'monitors:dispatch-checks';

    protected $description = 'Dispatch PerformCheck jobs for every monitor that is due for a check.';

    public function handle(): int
    {
        $due = Monitor::query()
            ->where('is_paused', false)
            ->get()
            ->filter(fn (Monitor $m) => $this->isDue($m));

        foreach ($due as $monitor) {
            PerformCheck::dispatch($monitor);
        }

        $count = $due->count();

        $this->info("Dispatched {$count} check job(s).");

        return self::SUCCESS;
    }

    private function isDue(Monitor $monitor): bool
    {
        if ($monitor->last_checked_at === null) {
            return true;
        }

        return $monitor->last_checked_at
            ->addMinutes($monitor->interval_minutes)
            ->isPast();
    }
}
