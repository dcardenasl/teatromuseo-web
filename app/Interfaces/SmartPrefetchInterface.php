<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Prefetches external data required by CMS page blocks in parallel.
 *
 * Used in Fase 4 (Smart Prefetch) to batch multiple API calls and avoid
 * N+1 queries during block rendering by pre-loading all data upfront.
 */
interface SmartPrefetchInterface
{
    /**
     * Prefetch data for all block requirements in parallel.
     *
     * Takes requirements from BlockAnalyzerService (which resource types and IDs
     * are needed) and makes parallel API calls to fetch all data at once,
     * returning results keyed by resource type.
     *
     * Example return:
     *   [
     *     'collection_items' => [
     *       1 => ['id' => 1, 'name' => 'Item 1', ...],
     *       2 => ['id' => 2, 'name' => 'Item 2', ...],
     *     ],
     *     'events' => [
     *       10 => ['id' => 10, 'title' => 'Festival', ...],
     *     ],
     *   ]
     *
     * @param array<string, array{ids?: array<int>, slugs?: array<string>, fields?: array<string>}> $requirements From BlockAnalyzerService::analyze()
     * @param string $locale Current render locale
     * @return array<string, array<int|string, array<string, mixed>>> Fetched data keyed by type then by ID/slug
     */
    public function prefetch(array $requirements, string $locale = 'es'): array;

    /**
     * Prefetch a single resource type by batch of IDs.
     *
     * @param string $resourceType Resource type (collection_items, events, etc.)
     * @param array<int> $ids IDs to fetch
     * @param array<string> $fields Sparse fields to request
     * @param string $locale Current render locale
     * @return array<int, array<string, mixed>> Fetched data keyed by ID
     */
    public function prefetchBatch(string $resourceType, array $ids, array $fields = [], string $locale = 'es'): array;
}
