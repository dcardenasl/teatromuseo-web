<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Contract for the public-site HTTP client against the Domain CMS API.
 *
 * All methods degrade gracefully: they never throw on transport or upstream
 * errors and always return the normalized result envelope below, so callers
 * (services, controllers) only need to check `ok`.
 */
interface WebApiClientInterface
{
    /**
     * GET request with server-side caching.
     *
     * @param array<string, mixed> $query
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    public function get(string $path, array $query = [], int $cacheTtl = 300, string $scope = 'general'): array;

    /**
     * Batch GET requests executed in parallel when missing from cache.
     *
     * @param list<array{path: string, query?: array<string, mixed>, cacheTtl?: int, scope?: string}> $requests
     *
     * @return list<array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}>
     */
    public function multiGet(array $requests): array;

    /**
     * POST request — never cached.
     *
     * @param array<string, mixed> $data
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    public function post(string $path, array $data = []): array;
}
