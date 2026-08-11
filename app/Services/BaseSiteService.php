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
    /**
     * @var array<string, array<int, string>>
     */
    private array $collectionSlugsByLocale = [];

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

    /**
     * Resolve a collection-specific public slug when the navigation payload
     * only contains the collection ID. Older CMS responses omit
     * `collection_slug`, so keeping this fallback in the shared site-service
     * base prevents menu consumers from collapsing every item to /museo/coleccion.
     *
     * @param array<string, mixed> $navigation
     */
    protected function resolveCollectionSlug(string $locale, array $navigation): string
    {
        $collectionSlug = trim((string) ($navigation['collection_slug'] ?? ''), '/');
        if ($collectionSlug !== '') {
            return $collectionSlug;
        }

        $targetType = (string) ($navigation['target_type'] ?? '');
        if (! in_array($targetType, ['collection', 'collection_index', 'collection_listing'], true)) {
            return '';
        }

        $collectionId = (int) ($navigation['target_id'] ?? 0);
        if ($collectionId <= 0) {
            return '';
        }

        if (! array_key_exists($locale, $this->collectionSlugsByLocale)) {
            $this->collectionSlugsByLocale[$locale] = [];
            $collections = $this->fetchData("public/{$locale}/collections", [], 3600, 'collections') ?? [];

            foreach ($collections as $collection) {
                if (! is_array($collection)) {
                    continue;
                }

                $id = (int) ($collection['id'] ?? 0);
                $localizedSlugs = is_array($collection['localized_slugs'] ?? null)
                    ? $collection['localized_slugs']
                    : [];
                $slug = trim((string) ($localizedSlugs[$locale] ?? $collection['slug'] ?? ''), '/');

                if ($id > 0 && $slug !== '') {
                    $this->collectionSlugsByLocale[$locale][$id] = $slug;
                }
            }
        }

        return $this->collectionSlugsByLocale[$locale][$collectionId] ?? '';
    }

    /**
     * Normalize the compact pagination contract used by the domain APIs into
     * the richer shape expected by the public site and block templates.
     *
     * @param array<string, mixed> $meta
     * @return array{total: int, total_items: int, page: int, current_page: int, per_page: int, total_pages: int, has_next_page: bool, has_previous_page: bool}
     */
    protected function normalizePagination(array $meta, int $defaultPage = 1, int $defaultPerPage = 20): array
    {
        $total = (int) ($meta['total'] ?? $meta['total_items'] ?? $meta['count'] ?? 0);
        $page = (int) ($meta['page'] ?? $meta['current_page'] ?? $defaultPage);
        $perPage = (int) ($meta['per_page'] ?? $meta['perPage'] ?? $defaultPerPage);

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
}
