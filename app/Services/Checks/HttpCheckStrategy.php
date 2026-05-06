<?php

declare(strict_types=1);

namespace App\Services\Checks;

use App\Models\Monitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpCheckStrategy implements CheckStrategy
{
    public function run(Monitor $monitor): CheckExecutionResult
    {
        $startNs = hrtime(true);

        try {
            $response = Http::timeout(10)->{strtolower($monitor->method)}($monitor->url);

            return new CheckExecutionResult(
                statusCode: $response->status(),
                responseTimeMs: (int) round((hrtime(true) - $startNs) / 1_000_000),
                isSuccessful: $response->successful(),
                responseBody: $response->body(),
            );
        } catch (ConnectionException) {
            return new CheckExecutionResult(
                statusCode: null,
                responseTimeMs: (int) round((hrtime(true) - $startNs) / 1_000_000),
                isSuccessful: false,
                responseBody: null,
            );
        }
    }
}
