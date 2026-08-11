<?php

declare(strict_types=1);

namespace App\Analytics;

final readonly class AnalyticsTransportResult
{
    public function __construct(
        public bool $accepted,
        public bool $retryable,
        public ?int $statusCode = null,
    ) {
    }

    public static function accepted(?int $statusCode = null): self
    {
        return new self(true, false, $statusCode);
    }

    public static function retryable(?int $statusCode = null): self
    {
        return new self(false, true, $statusCode);
    }

    public static function rejected(?int $statusCode = null): self
    {
        return new self(false, false, $statusCode);
    }
}
