<?php

namespace App\Jobs;

use App\Models\Monitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PerformCheck implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /** Maximum attempts before the job is failed. */
    public int $tries = 3;

    /** Exponential backoff in seconds between retries: 10s, 100s, 1000s. */
    public array $backoff = [10, 100, 1000];

    public function __construct(public readonly Monitor $monitor)
    {
        $this->onQueue('checks');
    }

    /** One unique job per monitor — prevents duplicate dispatches in the queue. */
    public function uniqueId(): int
    {
        return $this->monitor->id;
    }

    public function handle(): void
    {
        $startedAt  = now();
        $startNs    = hrtime(true);
        $statusCode = null;
        $isSuccessful = false;
        $responseTimeMs = 0;

        try {
            $response = Http::timeout(10)->{strtolower($this->monitor->method)}($this->monitor->url);

            $responseTimeMs = (int) round((hrtime(true) - $startNs) / 1_000_000);
            $statusCode     = $response->status();
            $isSuccessful   = $response->successful(); // 2xx

        } catch (ConnectionException) {
            // DNS failure, refused connection, or timeout
            $responseTimeMs = (int) round((hrtime(true) - $startNs) / 1_000_000);

        } catch (RequestException $e) {
            // HTTP error response received (4xx / 5xx thrown by throw())
            $responseTimeMs = (int) round((hrtime(true) - $startNs) / 1_000_000);
            $statusCode     = $e->response->status();
            $isSuccessful   = false;
        }

        $this->monitor->checkResults()->create([
            'status_code'      => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_successful'    => $isSuccessful,
            'checked_at'       => $startedAt,
        ]);

        $this->monitor->update([
            'last_checked_at' => $startedAt,
            'current_status'  => $isSuccessful ? 'up' : 'down',
        ]);
    }
}
