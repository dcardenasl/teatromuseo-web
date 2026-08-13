<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

use App\Libraries\WebApiClientInterface;

/**
 * Decides which outbound requests a single plan needs and queues them.
 * Detail blocks (event/catalog item) resolve an id-or-slug reference and
 * queue one lookup, or reuse a controller-seeded item with none at all.
 * List blocks (collection_grid/listing/timeline) resolve their source
 * collection when needed and queue the paginated listing request, plus any
 * category/tag facet requests the block config asks for.
 */
final class BlockRequestPlanner
{
    /**
     * @param array<string, WebApiClientInterface> $clients
     */
    public function __construct(
        private readonly array $clients,
        private readonly ListQueryBuilder $queryBuilder,
        private readonly BlockResultMaterializer $results,
    ) {
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, list<array<string, mixed>>> $seededItems Items
     * already loaded by the owning controller, grouped by source type.
     */
    public function planInitial(array &$plan, string $locale, PrefetchRequestQueue $queue, array $seededItems = []): void
    {
        if ($plan['kind'] === 'detail') {
            $this->planDetail($plan, $locale, $queue, $seededItems);

            return;
        }

        $sourceType = $plan['source_type'];
        if (! in_array($sourceType, ['cms_collection', 'catalog_items', 'event_items'], true)) {
            $plan['result'] = $this->results->failedResult(422, 'Dynamic block has an invalid source type.');

            return;
        }

        if ($sourceType === 'cms_collection') {
            $collectionId = max(0, (int) ($plan['payload']['collection_id'] ?? 0));
            $collectionKey = trim((string) ($plan['payload']['collection_key'] ?? ''));
            $plan['collection_id'] = $collectionId;
            $plan['collection_key'] = $collectionKey;
            if ($collectionId > 0) {
                $plan['collection_index'] = $queue->add(
                    'cms',
                    'public/' . rawurlencode($locale) . '/collections',
                    [],
                    3600,
                    'collections',
                );
            }
            if ($collectionKey === '') {
                return;
            }
        }

        if ($sourceType === 'catalog_items' && $this->queryBuilder->catalogNeedsCategoryDependency($plan)) {
            $plan['dependency_indexes']['categories'] = $queue->add(
                'catalog',
                'public/catalog/categories',
                [],
                600,
                'categories',
            );

            return;
        }

        $this->addListRequests($plan, $locale, $queue);
    }

    /** @param array<string, mixed> $plan */
    public function addListRequests(array &$plan, string $locale, PrefetchRequestQueue $queue): void
    {
        $sourceType = $plan['source_type'];
        $query = $this->queryBuilder->build($plan, $sourceType);
        $client = 'cms';
        $scope = 'entries';

        if ($sourceType === 'event_items') {
            $client = 'event';
            $path = 'public-read/' . rawurlencode($locale) . '/events';
            $scope = 'events';
        } elseif ($sourceType === 'catalog_items') {
            $client = 'catalog';
            $path = 'public-read/' . rawurlencode($locale) . '/collection-items';
            $scope = 'collection_items';
        } else {
            $collectionKey = trim((string) ($plan['collection_key'] ?? ''));
            if ($collectionKey === '') {
                $plan['result'] = $this->results->failedResult(422, 'CMS collection key could not be resolved.');

                return;
            }
            $path = 'public-read/' . rawurlencode($locale) . '/entries/' . rawurlencode($collectionKey);
        }

        $plan['main_index'] = $queue->add($client, $path, $query, 180, $scope);
        $plan['main_query'] = $query;

        $showCategories = $this->queryBuilder->wantsFacet($plan, 'categories');
        $showTags = $this->queryBuilder->wantsFacet($plan, 'tags');
        if ($plan['block_key'] !== 'collection_listing' || (! $showCategories && ! $showTags)) {
            return;
        }

        if ($showCategories && $sourceType === 'cms_collection') {
            $plan['facet_indexes']['categories'] = $queue->add(
                'cms',
                'public/' . rawurlencode($locale) . '/categories/' . rawurlencode((string) $plan['collection_key']),
                [],
                600,
                'taxonomies',
            );
        } elseif ($showCategories && $sourceType === 'catalog_items') {
            $plan['facet_indexes']['categories'] = $queue->add('catalog', 'public/catalog/categories', [], 600, 'categories');
        }

        if ($showTags && $sourceType === 'cms_collection') {
            $plan['facet_indexes']['tags'] = $queue->add(
                'cms',
                'public/' . rawurlencode($locale) . '/tags/' . rawurlencode((string) $plan['collection_key']),
                [],
                600,
                'taxonomies',
            );
        } elseif ($showTags && $sourceType === 'event_items') {
            $plan['facet_indexes']['tags'] = $queue->add('event', 'public/events/types', [], 600, 'event_types');
        }
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, list<array<string, mixed>>> $seededItems
     */
    private function planDetail(array &$plan, string $locale, PrefetchRequestQueue $queue, array $seededItems): void
    {
        $reference = $this->detailReference($plan['payload'], (string) $plan['block_key']);
        if ($reference === null) {
            $plan['result'] = $this->results->failedResult(422, 'Dynamic detail block has no id or slug.');

            return;
        }

        $definition = str_starts_with((string) $plan['block_key'], 'event_item_')
            ? [
                'client' => 'event',
                'endpoint' => 'public-read/' . rawurlencode($locale) . '/events',
                'scope' => 'events',
            ]
            : [
                'client' => 'catalog',
                'endpoint' => 'public-read/' . rawurlencode($locale) . '/collection-items',
                'scope' => 'collection_items',
            ];

        $seededItem = $this->findSeededItem($seededItems, $definition['client'], $reference['value']);
        if ($seededItem !== null) {
            $plan['seeded_item'] = $seededItem;

            return;
        }

        if (! isset($this->clients[$definition['client']])) {
            $plan['result'] = $this->results->failedResult(503, 'Dynamic detail client is unavailable.');

            return;
        }

        $query = ['fields' => implode(',', $this->detailFields($plan['source_type']))];
        $path = $definition['endpoint'] . '/' . rawurlencode($reference['value']);

        $plan['main_index'] = $queue->add($definition['client'], $path, $query, 300, $definition['scope']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{kind: 'id'|'slug', value: string}|null
     */
    private function detailReference(array $payload, string $blockKey): ?array
    {
        $idKeys = str_starts_with($blockKey, 'event_item_')
            ? ['event_id']
            : ['collection_item_id'];
        foreach ($idKeys as $key) {
            if (isset($payload[$key]) && (is_int($payload[$key]) || ctype_digit((string) $payload[$key])) && (int) $payload[$key] > 0) {
                return ['kind' => 'id', 'value' => (string) (int) $payload[$key]];
            }
        }
        $slugKeys = str_starts_with($blockKey, 'event_item_')
            ? ['event_slug']
            : ['collection_item_slug'];
        foreach ($slugKeys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return ['kind' => 'slug', 'value' => $value];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function detailFields(string $sourceType): array
    {
        return $sourceType === 'event_items'
            ? ['id', 'uuid', 'title', 'event_type', 'description', 'slug', 'slugs', 'cover_file_id', 'cover_image', 'gallery_file_ids', 'gallery_images', 'translations', 'localized', 'occurrences', 'status', 'created_at', 'updated_at']
            : ['id', 'name', 'category_id', 'inventory_code', 'status', 'summary', 'curiosidad', 'contenido', 'origin', 'period', 'creator', 'ubicacion', 'materials', 'cover_file_id', 'cover_image', 'gallery_file_ids', 'gallery_images', 'collection_number', 'collection_group', 'physical_description', 'dimensions', 'ingress_type', 'donated_by', 'tags', 'links', 'company_history', 'localized', 'translations', 'slug', 'slugs', 'techniques', 'created_at', 'updated_at'];
    }

    /**
     * Match a controller-loaded detail against either the id/code or slug used
     * by the template block. This keeps the seed contract independent of the
     * route's public identifier shape.
     *
     * @param array<string, list<array<string, mixed>>> $seededItems
     * @return array<string, mixed>|null
     */
    private function findSeededItem(array $seededItems, string $client, string $reference): ?array
    {
        $sourceType = $client === 'event' ? 'event_items' : 'catalog_items';
        $candidates = $seededItems[$sourceType] ?? [];
        if (! is_array($candidates)) {
            return null;
        }

        foreach ($candidates as $item) {
            if (! is_array($item)) {
                continue;
            }

            $identifiers = [
                (string) ($item['id'] ?? ''),
                (string) ($item['uuid'] ?? ''),
                (string) ($item['inventory_code'] ?? ''),
                (string) ($item['slug'] ?? ''),
            ];
            $slugs = is_array($item['slugs'] ?? null) ? $item['slugs'] : [];
            foreach ($slugs as $slug) {
                if (is_scalar($slug)) {
                    $identifiers[] = (string) $slug;
                }
            }

            if (in_array($reference, array_filter($identifiers), true)) {
                return $item;
            }
        }

        return null;
    }
}
