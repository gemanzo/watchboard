<?php

namespace App\Jobs;

use App\Events\CheckCompleted;
use App\Events\MonitorStatusChanged;
use App\Models\CheckResult;
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
        $oldStatus  = $this->monitor->current_status;
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

        [$newStatus, $newConsecutiveFailures] = $this->resolveStatusAndCounter($isSuccessful, $oldStatus);

        $checkResult = $this->monitor->checkResults()->create([
            'status_code'      => $statusCode,
            'response_time_ms' => $responseTimeMs,
            'is_successful'    => $isSuccessful,
            'checked_at'       => $startedAt,
        ]);

        $this->monitor->update([
            'last_checked_at'      => $startedAt,
            'current_status'       => $newStatus,
            'consecutive_failures' => $newConsecutiveFailures,
        ]);

        $this->dispatchStatusChangedIfNeeded($oldStatus, $newStatus, $checkResult);

        CheckCompleted::dispatch($this->monitor, $checkResult);
    }

    /**
     * Determine the new status and consecutive-failure counter after a check.
     *
     * A failure only flips the status to 'down' once consecutive_failures
     * reaches confirmation_threshold, preventing noisy alerts for transient
     * network blips. A successful check always resets the counter to 0.
     *
     * @return array{0: string, 1: int} [newStatus, newConsecutiveFailures]
     */
    private function resolveStatusAndCounter(bool $isSuccessful, string $oldStatus): array
    {
        if ($isSuccessful) {
            return ['up', 0];
        }

        $newConsecutiveFailures = $this->monitor->consecutive_failures + 1;

        $newStatus = ($newConsecutiveFailures >= $this->monitor->confirmation_threshold)
            ? 'down'
            : $oldStatus;

        return [$newStatus, $newConsecutiveFailures];
    }

    private function dispatchStatusChangedIfNeeded(
        string      $oldStatus,
        string      $newStatus,
        CheckResult $checkResult,
    ): void {
        // No change in effective status
        if ($oldStatus === $newStatus) {
            return;
        }

        // First check with the service already up — not a meaningful transition
        if ($oldStatus === 'unknown' && $newStatus === 'up') {
            return;
        }

        $downtimeSeconds = null;

        if ($oldStatus === 'down' && $newStatus === 'up') {
            $downtimeSeconds = $this->calculateDowntimeSeconds($checkResult);
        }

        MonitorStatusChanged::dispatch(
            $this->monitor,
            $oldStatus,
            $newStatus,
            $checkResult,
            $downtimeSeconds,
        );
    }

    /**
     * Calculate how long the monitor was down before this recovery.
     *
     * We look for the most recent successful check before this one; the time
     * elapsed since that check is a conservative approximation of the downtime.
     * Returns null when no prior successful check exists (downtime started
     * before the first recorded result).
     */
    private function calculateDowntimeSeconds(CheckResult $checkResult): ?int
    {
        $lastUp = $this->monitor->checkResults()
            ->where('is_successful', true)
            ->where('id', '<>', $checkResult->id)
            ->latest('checked_at')
            ->first();

        if ($lastUp === null) {
            return null;
        }

        return (int) abs($checkResult->checked_at->diffInSeconds($lastUp->checked_at));
    }
}
