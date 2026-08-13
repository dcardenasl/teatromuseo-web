<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

use App\Services\SiteCatalogService;
use App\Services\SiteEventService;

/**
 * Builds the domain query array for a list-kind plan (collection_grid,
 * collection_listing, collection_timeline). Pure translation from a block's
 * config + the current request's filter/sort/pagination params into the
 * exact query the owning domain (CMS, Catalog, Event) expects.
 */
final class ListQueryBuilder
{
    public function __construct(private readonly RequestQueryReader $requestQuery)
    {
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function build(array $plan, string $sourceType): array
    {
        $payload = $plan['payload'];
        $blockKey = (string) $plan['block_key'];
        $isListing = $blockKey === 'collection_listing';
        $limitDefault = $blockKey === 'collection_timeline' ? 100 : ($isListing ? 12 : 3);
        $configuredLimit = (int) ($payload['per_page'] ?? $payload['items_limit'] ?? $limitDefault);
        $requestLimit = $isListing ? $this->requestQuery->value('limit', $this->requestQuery->value('per_page')) : '';
        $limit = $configuredLimit;
        if ($requestLimit !== '' && ctype_digit($requestLimit) && (int) $requestLimit > 0) {
            $limit = (int) $requestLimit;
        }
        $limit = max(1, min(100, $limit));
        $page = $isListing ? max(1, (int) $this->requestQuery->value('page', '1')) : 1;
        $projection = $payload['listing_projection'] ?? [];
        if (is_string($projection)) {
            $projection = json_decode($projection, true);
        }
        $projection = is_array($projection) ? $projection : [];
        $projectionOrder = is_array($projection['order'] ?? null) ? $projection['order'] : [];
        $publicOrdering = $this->truthy($projectionOrder['public'] ?? false);
        $configuredOrder = trim((string) ($payload['order_by'] ?? $projectionOrder['field'] ?? ''));
        $configuredDirection = strtolower((string) ($payload['order_direction'] ?? $projectionOrder['direction'] ?? 'desc'));
        $allowedDirections = $sourceType === 'cms_collection' ? ['asc', 'desc', 'upcoming'] : ['asc', 'desc'];
        $direction = in_array($configuredDirection, $allowedDirections, true) ? $configuredDirection : 'desc';
        if ($isListing && $publicOrdering) {
            $requestedDirection = strtolower($this->requestQuery->value('order_direction'));
            if (in_array($requestedDirection, $allowedDirections, true)) {
                $direction = $requestedDirection;
            }
        }
        $orderBy = $configuredOrder;
        if ($isListing && $publicOrdering && $this->requestQuery->value('order_by') !== '') {
            $orderBy = $this->requestQuery->value('order_by');
        }
        if ($orderBy === '') {
            $orderBy = $blockKey === 'collection_timeline' ? 'published_at' : ($sourceType === 'catalog_items' ? 'name' : 'published_at');
        }

        if ($sourceType === 'event_items') {
            // Only collection_grid/collection_listing ever reach this branch
            // (collection_timeline is CMS-collection-only) — $isListing
            // cleanly picks the richer collection_listing card fields vs. the
            // minimal grid card. See
            // docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.C.
            $query = [
                'page' => $page,
                'per_page' => $limit,
                'sort' => 'agenda',
                'fields' => $isListing ? SiteEventService::LIST_FIELDS : SiteEventService::GRID_FIELDS,
            ];
            $sort = match ($orderBy) {
                'entry.title', 'title' => 'title',
                default => 'agenda',
            };
            $query['sort'] = $sort;
            if ($isListing && ($q = $this->requestQuery->value('q')) !== '') {
                $query['search'] = $q;
            }
            $tag = $isListing ? $this->requestQuery->value('tag') : '';
            if ($tag !== '') {
                $query['event_type'] = $tag;
            }

            return $query;
        }

        if ($sourceType === 'catalog_items') {
            $sort = match ($orderBy) {
                'entry.title', 'title', 'name' => 'name',
                'entry.slug', 'slug' => 'slug',
                'entry.origin', 'origin' => 'origin',
                'entry.period', 'period' => 'period',
                default => 'name',
            };
            $query = [
                'page' => $page,
                'per_page' => $limit,
                'sort' => $sort,
                'fields' => $isListing ? SiteCatalogService::LIST_FIELDS : SiteCatalogService::GRID_FIELDS,
            ];
            $categoryId = max(0, (int) ($plan['category_id'] ?? $payload['category_id'] ?? 0));
            if ($categoryId > 0) {
                $query['category_id'] = $categoryId;
            }
            if ($isListing && ($q = $this->requestQuery->value('q')) !== '') {
                $query['search'] = $q;
            }

            return $query;
        }

        // Mirrors the per-block-key listing_content sub-selection each
        // ViewModel/Source actually consumes (CollectionGridViewModel::entries(),
        // CollectionTimelineViewModel::entries(), CmsCollectionSource::fetch())
        // — the prefetch query must match exactly, or the ViewModel's own
        // (narrower) query misses this cache entry and both a prefetch and a
        // live fetch happen. See
        // docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.C.
        $include = match ($blockKey) {
            'collection_grid' => 'listing_content.fields',
            'collection_timeline' => 'listing_content.publication_date,listing_content.documents',
            default => 'listing_content.image,listing_content.secondary_action,listing_content.rich_text,listing_content.video,listing_content.publication_date,listing_content.date_fields,listing_content.fields',
        };
        $query = [
            'page' => $page,
            'per_page' => $limit,
            'order_by' => $this->cmsOrderField($orderBy),
            'order_direction' => $direction,
            'include' => $include,
        ];
        if ($isListing) {
            foreach (['category', 'tag', 'q', 'filter_by', 'filter_value', 'filter_operator'] as $key) {
                $value = $this->requestQuery->value($key);
                if ($value !== '' && ($key !== 'filter_operator' || $value === 'contains')) {
                    $query[$key] = $value;
                }
            }
        } elseif (($categoryId = max(0, (int) ($payload['category_id'] ?? 0))) > 0) {
            $query['category_id'] = $categoryId;
        }
        $fields = array_values(array_unique([
            'id', 'slug', 'title', 'excerpt', 'published_at', 'featured_image', 'listing_content',
        ]));
        if ($fields !== []) {
            $query['fields'] = implode(',', $fields);
        }

        return $query;
    }

    /** @param array<string, mixed> $plan */
    public function catalogNeedsCategoryDependency(array $plan): bool
    {
        return $plan['kind'] === 'list'
            && $this->categoryValue($plan) !== '';
    }

    /** @param array<string, mixed> $plan */
    public function categoryValue(array $plan): string
    {
        $payload = is_array($plan['payload'] ?? null) ? $plan['payload'] : [];
        $configured = trim((string) ($payload['category'] ?? ''));

        return $configured !== '' ? $configured : $this->requestQuery->value('category');
    }

    /** @param array<string, mixed> $plan */
    public function wantsFacet(array $plan, string $facet): bool
    {
        $key = $facet === 'categories' ? 'show_categories' : 'show_tags';
        $payload = is_array($plan['payload'] ?? null) ? $plan['payload'] : [];
        if (array_key_exists($key, $payload)) {
            return $this->truthy($payload[$key]);
        }

        return $facet === 'categories'
            ? in_array($plan['source_type'] ?? '', ['cms_collection', 'catalog_items'], true)
            : ($plan['source_type'] ?? '') === 'event_items';
    }

    private function cmsOrderField(string $orderBy): string
    {
        return str_starts_with($orderBy, 'entry.')
            || str_starts_with($orderBy, 'block.')
            || str_starts_with($orderBy, 'taxonomy.')
            ? 'field:' . $orderBy
            : $orderBy;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
