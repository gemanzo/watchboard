<?php

declare(strict_types=1);

namespace App\Services\Checks;

use App\Models\Monitor;

final class PingCheckStrategy implements CheckStrategy
{
    public function run(Monitor $monitor): CheckExecutionResult
    {
        $host = $this->extractHost($monitor->url);
        $command = sprintf('ping -c 1 -W 2 %s 2>&1', escapeshellarg($host));

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        $responseTimeMs = $this->extractResponseTimeMs($output);

        return new CheckExecutionResult(
            statusCode: null,
            responseTimeMs: $responseTimeMs ?? 0,
            isSuccessful: $exitCode === 0,
        );
    }

    /**
     * @param array<int, string> $output
     */
    private function extractResponseTimeMs(array $output): ?int
    {
        $text = implode("\n", $output);

        if (preg_match('/time[=<]([0-9]+(?:\.[0-9]+)?)\s*ms/i', $text, $matches) !== 1) {
            return null;
        }

        return (int) round((float) $matches[1]);
    }

    private function extractHost(string $value): string
    {
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $value;
    }
}
