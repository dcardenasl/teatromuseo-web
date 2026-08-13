<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

/**
 * Second-wave planning: once the first request wave returns, resolve any
 * plan whose listing request depended on that response — a CMS collection
 * resolved by id, or a catalog category resolved by slug — and queue the
 * now-unblocked listing request. Plans whose first wave already queued
 * their listing request (or that are detail blocks, which never depend on
 * anything else) are left untouched.
 */
final class BlockDependencyResolver
{
    public function __construct(
        private readonly BlockRequestPlanner $requestPlanner,
        private readonly ListQueryBuilder $queryBuilder,
        private readonly BlockResultMaterializer $results,
    ) {
    }

    /**
     * @param array<int|string, array<string, mixed>> $plans
     * @param array<int, array<string, mixed>> $responses
     * @param-out array<int|string, array<string, mixed>> $plans
     */
    public function resolve(array &$plans, array $responses, string $locale, PrefetchRequestQueue $queue): void
    {
        foreach ($plans as &$plan) {
            if ($plan['kind'] === 'detail' || $plan['main_index'] !== null) {
                continue;
            }

            $collectionKey = trim((string) ($plan['collection_key'] ?? ''));
            if ($plan['collection_index'] !== null) {
                $collectionResponse = $responses[$plan['collection_index']] ?? null;
                $collection = $this->findCollection(
                    $collectionResponse,
                    (int) ($plan['collection_id'] ?? 0),
                    (string) ($plan['collection_key'] ?? ''),
                );
                if ($collection !== null) {
                    $plan['collection'] = $collection;
                    $plan['collection_key'] = $collectionKey = trim((string) ($collection['collection_key'] ?? ''));
                }
            }

            if ($plan['source_type'] === 'catalog_items') {
                $category = $this->queryBuilder->categoryValue($plan);
                if ($category !== '') {
                    $categoryResponse = isset($plan['dependency_indexes']['categories'])
                        ? ($responses[$plan['dependency_indexes']['categories']] ?? null)
                        : null;
                    $plan['category_id'] = $this->findCategoryId($categoryResponse, $category);
                }
            }

            if ($plan['source_type'] === 'cms_collection' && $collectionKey === '') {
                $plan['result'] = $this->results->failedResult(404, 'CMS collection was not found.');

                continue;
            }

            $this->requestPlanner->addListRequests($plan, $locale, $queue);
        }
        unset($plan);
    }

    /**
     * @param array<string, mixed>|null $response
     * @return array<string, mixed>|null
     */
    private function findCollection(?array $response, int $collectionId, string $collectionKey = ''): ?array
    {
        if (! is_array($response) || ! ($response['ok'] ?? false)) {
            return null;
        }
        foreach ($this->results->items($response['data'] ?? null) as $collection) {
            if ($collectionId > 0 && (int) ($collection['id'] ?? 0) === $collectionId) {
                return $collection;
            }
            if ($collectionKey !== '' && (string) ($collection['collection_key'] ?? '') === $collectionKey) {
                return $collection;
            }
        }

        return null;
    }

    /** @param array<string, mixed>|null $response */
    private function findCategoryId(?array $response, string $slug): int
    {
        if (! is_array($response) || ! ($response['ok'] ?? false)) {
            return 0;
        }
        foreach ($this->results->items($response['data'] ?? null) as $category) {
            if (trim((string) ($category['slug'] ?? ''), '/') === trim($slug, '/')) {
                return max(0, (int) ($category['id'] ?? 0));
            }
        }

        return 0;
    }
}
