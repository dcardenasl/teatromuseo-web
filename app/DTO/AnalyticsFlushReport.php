<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class AnalyticsFlushReport
{
    public function __construct(
        public int $processed,
        public int $sent,
        public int $retrying,
        public int $failed,
        public int $deferred,
        public int $remaining,
        public bool $locked = false,
    ) {
    }

    public static function locked(): self
    {
        return new self(0, 0, 0, 0, 0, 0, true);
    }
}
