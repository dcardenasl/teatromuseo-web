<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

use App\Libraries\WebApiClient;
use App\Libraries\WebApiClientInterface;

/**
 * Dispatches a queued batch of requests. Prefers `WebApiClient::multiGetAcross()`
 * (one real `curl_multi` wave spanning every client/domain) whenever every
 * request targets a native `WebApiClient`; falls back to per-client
 * `multiGet()` grouping only for injected test doubles that don't support
 * cross-client batching.
 *
 * @phpstan-import-type PrefetchRequest from PrefetchRequestQueue
 */
final class PrefetchRequestExecutor
{
    /**
     * @param array<string, WebApiClientInterface> $clients
     */
    public function __construct(
        private readonly array $clients,
        private readonly BlockResultMaterializer $results,
    ) {
    }

    /**
     * @param list<PrefetchRequest> $requests
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $requests, int $offset = 0): array
    {
        if ($requests === [] || $offset >= count($requests)) {
            return [];
        }

        $pending = array_slice($requests, $offset);
        $responses = [];
        $allNative = true;
        foreach ($pending as $request) {
            $client = $this->clients[(string) ($request['client'] ?? '')] ?? null;
            if (! $client instanceof WebApiClient) {
                $allNative = false;
                break;
            }
        }

        if ($allNative) {
            /** @var list<array{client: WebApiClient, path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> $nativeRequests */
            $nativeRequests = [];
            foreach ($pending as $request) {
                $client = $this->clients[(string) $request['client']] ?? null;
                if (! $client instanceof WebApiClient) {
                    continue;
                }
                $nativeRequests[] = [
                    'client' => $client,
                    'path' => (string) $request['path'],
                    'query' => is_array($request['query']) ? $request['query'] : [],
                    'cacheTtl' => (int) $request['cacheTtl'],
                    'scope' => (string) $request['scope'],
                ];
            }
            foreach (WebApiClient::multiGetAcross($nativeRequests) as $index => $response) {
                $responses[$offset + $index] = $response;
            }

            return $responses;
        }

        $grouped = [];
        foreach ($pending as $index => $request) {
            $grouped[(string) $request['client']][] = [$index, $request];
        }
        foreach ($grouped as $clientKey => $group) {
            $client = $this->clients[$clientKey] ?? null;
            if (! $client instanceof WebApiClientInterface) {
                foreach ($group as [$index]) {
                    $responses[$offset + $index] = $this->results->failedResult(503, 'Prefetch client is unavailable.');
                }
                continue;
            }
            /** @var list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> $batch */
            $batch = [];
            foreach ($group as $entry) {
                $request = $entry[1];
                $batch[] = [
                    'path' => (string) $request['path'],
                    'query' => is_array($request['query']) ? $request['query'] : [],
                    'cacheTtl' => (int) $request['cacheTtl'],
                    'scope' => (string) $request['scope'],
                ];
            }
            foreach ($client->multiGet($batch) as $index => $response) {
                $responses[$offset + $group[$index][0]] = is_array($response)
                    ? $response
                    : $this->results->failedResult(502, 'Invalid prefetch response.');
            }
        }

        return $responses;
    }
}
