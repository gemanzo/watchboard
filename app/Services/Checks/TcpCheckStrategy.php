<?php

declare(strict_types=1);

namespace App\Services\Checks;

use App\Models\Monitor;

final class TcpCheckStrategy implements CheckStrategy
{
    public function run(Monitor $monitor): CheckExecutionResult
    {
        $host = $this->extractHost($monitor->url);
        $port = (int) $monitor->port;
        $startNs = hrtime(true);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        $responseTimeMs = (int) round((hrtime(true) - $startNs) / 1_000_000);

        if (is_resource($socket)) {
            fclose($socket);

            return new CheckExecutionResult(
                statusCode: null,
                responseTimeMs: $responseTimeMs,
                isSuccessful: true,
            );
        }

        return new CheckExecutionResult(
            statusCode: null,
            responseTimeMs: $responseTimeMs,
            isSuccessful: false,
        );
    }

    private function extractHost(string $value): string
    {
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $value;
    }
}
