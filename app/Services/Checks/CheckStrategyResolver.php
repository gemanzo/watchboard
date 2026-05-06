<?php

declare(strict_types=1);

namespace App\Services\Checks;

use App\Models\Monitor;
use InvalidArgumentException;

class CheckStrategyResolver
{
    public function __construct(
        private HttpCheckStrategy $httpStrategy,
        private TcpCheckStrategy $tcpStrategy,
        private PingCheckStrategy $pingStrategy,
    ) {}

    public function resolve(Monitor $monitor): CheckStrategy
    {
        return match ($monitor->check_type) {
            'http' => $this->httpStrategy,
            'tcp' => $this->tcpStrategy,
            'ping' => $this->pingStrategy,
            default => throw new InvalidArgumentException('Unsupported check type: '.$monitor->check_type),
        };
    }
}
