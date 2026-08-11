<?php

declare(strict_types=1);

namespace App\Analytics;

use JsonException;

final class CurlAnalyticsTransport implements AnalyticsTransportInterface
{
    public function __construct(
        private readonly string $trackUrl,
        private readonly string $apiKey,
        private readonly int $timeoutMs = 5000,
        private readonly int $connectTimeoutMs = 1000,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function send(array $payload): AnalyticsTransportResult
    {
        try {
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return AnalyticsTransportResult::rejected();
        }

        $handle = curl_init($this->trackUrl);
        if ($handle === false) {
            return AnalyticsTransportResult::retryable();
        }

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST             => true,
                CURLOPT_POSTFIELDS       => $jsonPayload,
                CURLOPT_HTTPHEADER       => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-App-Key: ' . $this->apiKey,
                ],
                CURLOPT_RETURNTRANSFER   => true,
                CURLOPT_TIMEOUT_MS       => max(1000, $this->timeoutMs),
                CURLOPT_CONNECTTIMEOUT_MS => max(250, $this->connectTimeoutMs),
                CURLOPT_NOSIGNAL          => true,
            ]);

            $result = curl_exec($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($result === false) {
                return AnalyticsTransportResult::retryable($statusCode > 0 ? $statusCode : null);
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return AnalyticsTransportResult::accepted($statusCode);
            }

            if ($statusCode === 429 || $statusCode >= 500 || $statusCode === 0) {
                return AnalyticsTransportResult::retryable($statusCode > 0 ? $statusCode : null);
            }

            return AnalyticsTransportResult::rejected($statusCode);
        } finally {
            curl_close($handle);
        }
    }
}
