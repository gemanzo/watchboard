<?php

declare(strict_types=1);

namespace App\Services\Checks;

final readonly class CheckExecutionResult
{
    public function __construct(
        public ?int $statusCode,
        public int $responseTimeMs,
        public bool $isSuccessful,
        public ?string $responseBody = null,
    ) {}
}
