<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClientInterface;

/**
 * Base class for all Site*Service API adapters.
 *
 * Centralizes the constructor contract (interface-typed client, so tests can
 * inject fakes) and the shared "GET → null on failure" fetch pattern that
 * every read-only adapter repeats.
 */
abstract class BaseSiteService
{
    public function __construct(protected readonly WebApiClientInterface $apiClient)
    {
    }

    /**
     * Fetch `data` from a GET endpoint, or null when the request failed or
     * the payload is not an array.
     *
     * @param array<string, mixed> $query
     *
     * @return array<mixed>|null
     */
    protected function fetchData(string $path, array $query, int $cacheTtl, string $scope): ?array
    {
        $response = $this->apiClient->get($path, $query, $cacheTtl, $scope);

        if (! $response['ok']) {
            return null;
        }

        return is_array($response['data']) ? $response['data'] : null;
    }
}
