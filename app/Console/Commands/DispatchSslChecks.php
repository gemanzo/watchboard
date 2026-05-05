<?php

namespace App\Console\Commands;

use App\Jobs\PerformSslCheck;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;

class DispatchSslChecks extends Command
{
    protected $signature = 'monitors:dispatch-ssl-checks';

    protected $description = 'Dispatch PerformSslCheck jobs for every monitor that has SSL checking enabled.';

    public function handle(): int
    {
        $monitors = Monitor::query()
            ->where('is_paused', false)
            ->where('ssl_check_enabled', true)
            ->get();

        foreach ($monitors as $monitor) {
            app(Dispatcher::class)->dispatch(new PerformSslCheck($monitor));
        }

        $count = $monitors->count();

        $this->info("Dispatched {$count} SSL check job(s).");

        return self::SUCCESS;
    }
}
