<?php

declare(strict_types=1);

namespace App\Analytics;

interface AnalyticsTransportInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function send(array $payload): AnalyticsTransportResult;
}
