<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

/**
 * Accumulates the outbound requests a single page composition needs,
 * deduplicating identical ones (same client+locale+scope+path+query) so two
 * blocks that ask for the same data share one HTTP call. One instance is
 * scoped to a single `prefetchContext()`/`prefetch()` call — it is never
 * reused across requests, which is what lets it hold locale and accumulated
 * state as plain properties instead of parameters threaded by reference
 * through every planning method.
 *
 * @phpstan-type PrefetchRequest array{client: string, path: string, query: array<string, mixed>, cacheTtl: int, scope: string}
 */
final class PrefetchRequestQueue
{
    /** @var list<PrefetchRequest> */
    private array $requests = [];

    /** @var array<string, int> */
    private array $indexByIdentity = [];

    public function __construct(
        private readonly string $locale,
        private readonly RequestQueryReader $requestQuery,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function add(string $client, string $path, array $query, int $cacheTtl, string $scope): int
    {
        if ($this->requestQuery->isPreview()) {
            $query = array_merge($query, $this->requestQuery->previewQuery());
            $cacheTtl = 0;
        }

        $identity = $client . '|' . $this->locale . '|' . $scope . '|' . $path . '|' . md5((string) json_encode($this->sortRecursive($query)));
        if (isset($this->indexByIdentity[$identity])) {
            return $this->indexByIdentity[$identity];
        }

        $index = count($this->requests);
        $this->indexByIdentity[$identity] = $index;
        $this->requests[] = [
            'client' => $client,
            'path' => $path,
            'query' => $query,
            'cacheTtl' => $cacheTtl,
            'scope' => $scope,
        ];

        return $index;
    }

    public function count(): int
    {
        return count($this->requests);
    }

    /** @return list<PrefetchRequest> */
    public function all(): array
    {
        return $this->requests;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
