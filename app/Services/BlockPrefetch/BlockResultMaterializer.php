<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

/**
 * Turns a resolved plan (main response + facet responses, all already
 * fetched) into the block-result envelope ViewModels consume. No network
 * access happens here — this is pure data shaping.
 */
final class BlockResultMaterializer
{
    public function __construct(private readonly RequestQueryReader $requestQuery)
    {
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<int, array<string, mixed>> $responses
     * @return array<string, mixed>
     */
    public function materialize(array $plan, array $responses): array
    {
        $result = $plan['result'];
        $main = isset($plan['seeded_item'])
            ? [
                'ok' => true,
                'status' => 200,
                'data' => [$plan['seeded_item']],
                'meta' => [],
                'messages' => [],
            ]
            : ($plan['main_index'] !== null ? ($responses[$plan['main_index']] ?? null) : null);
        if (is_array($main)) {
            $result['ok'] = (bool) ($main['ok'] ?? false);
            $result['status'] = (int) ($main['status'] ?? 0);
            $result['data'] = $this->items($main['data'] ?? null);
            $result['meta'] = is_array($main['meta'] ?? null) ? $main['meta'] : [];
            if ($plan['kind'] === 'list' && ! isset($result['meta']['pagination'])) {
                $result['meta']['pagination'] = $this->normalizePagination(
                    $result['meta'],
                    is_array($plan['main_query'] ?? null) ? $plan['main_query'] : [],
                    count($result['data']),
                );
            }
            $result['stale'] = (bool) ($result['meta']['stale'] ?? false);
            $result['messages'] = $this->messages($main['messages'] ?? []);
        }

        if ($plan['kind'] === 'detail' && $result['data'] !== []) {
            $result['data'] = [$this->firstItem($result['data'])];
        }

        if (isset($plan['collection']) && is_array($plan['collection'])) {
            $result['collection'] = $plan['collection'];
        }

        foreach ($plan['facet_indexes'] as $facet => $index) {
            $response = $responses[$index] ?? null;
            $result['facets'][$facet] = is_array($response)
                ? $this->items($response['data'] ?? null)
                : [];
        }

        $result['instance'] = $this->instanceMetadata($plan, $result);

        return $result;
    }

    /** @return array<string, mixed> */
    public function emptyResult(): array
    {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'meta' => [],
            'facets' => ['categories' => [], 'tags' => []],
            'collection' => null,
            'stale' => false,
            'messages' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function failedResult(int $status, string $message): array
    {
        $result = $this->emptyResult();
        $result['status'] = $status;
        $result['messages'] = [$message];

        return $result;
    }

    /**
     * Preserve the full instance identity alongside the result envelope. The
     * block path is the stable discriminator when two blocks share a type.
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function instanceMetadata(array $plan, array $result): array
    {
        $query = is_array($plan['main_query'] ?? null) ? $plan['main_query'] : [];
        $payload = is_array($plan['payload'] ?? null) ? $plan['payload'] : [];
        $filters = is_array($query['filter'] ?? null) ? $query['filter'] : [];
        foreach (['category', 'tag', 'q', 'search', 'filter_by', 'filter_value', 'filter_operator'] as $key) {
            if (array_key_exists($key, $query)) {
                $filters[$key] = $query[$key];
            }
        }

        $state = ! ($result['ok'] ?? false)
            ? 'unavailable'
            : (($result['stale'] ?? false) === true ? 'stale' : 'fresh');

        return [
            'path' => (string) ($plan['block_path'] ?? ''),
            'type' => (string) ($plan['block_key'] ?? ''),
            'config' => $payload,
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'limit' => max(1, (int) ($query['per_page'] ?? $query['limit'] ?? 0)),
            'filters' => $filters,
            'order' => [
                'sort' => (string) ($query['sort'] ?? $query['order_by'] ?? ''),
                'direction' => (string) ($query['order_direction'] ?? ''),
            ],
            'facets' => array_values(array_map('strval', array_keys($plan['facet_indexes'] ?? []))),
            'preview' => $this->requestQuery->isPreview(),
            'source' => $state,
        ];
    }

    /**
     * Keep the block result contract compatible with the richer pagination
     * shape consumed by the listing templates, even when a domain response
     * only returns compact total/page/per_page metadata.
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizePagination(array $meta, array $query, int $dataCount): array
    {
        $total = (int) ($meta['total'] ?? $meta['total_items'] ?? $meta['count'] ?? $dataCount);
        $page = (int) ($meta['page'] ?? $meta['current_page'] ?? $query['page'] ?? 1);
        $perPage = (int) ($meta['per_page'] ?? $meta['perPage'] ?? $query['per_page'] ?? max(1, $dataCount));

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'total' => $total,
            'total_items' => $total,
            'page' => $page,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_next_page' => $page < $totalPages,
            'has_previous_page' => $page > 1,
        ];
    }

    /**
     * Normalize a raw API response payload to a list of records: unwraps a
     * nested `data` envelope, and wraps a single associative record into a
     * one-item list. Shared with BlockDependencyResolver, which scans the
     * same response shape for a specific collection/category.
     *
     * @return list<array<string, mixed>>
     */
    public function items(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        if (! array_is_list($data)) {
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function firstItem(array $items): array
    {
        return is_array($items[0] ?? null) ? $items[0] : [];
    }

    /** @return list<string> */
    private function messages(mixed $messages): array
    {
        return is_array($messages)
            ? array_values(array_filter($messages, 'is_string'))
            : [];
    }
}
