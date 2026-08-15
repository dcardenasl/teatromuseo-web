<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\Services\SiteCategoryService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteTagService;
use App\ViewModels\Blocks\Listing\ListingQuery;
use App\ViewModels\Blocks\Listing\ListingResult;
use App\ViewModels\Blocks\Listing\ListingSourceInterface;
use Closure;

class CmsCollectionSource implements ListingSourceInterface
{
    /** @var array<string, mixed>|null */
    private ?array $collection = null;

    public function __construct(
        private ?SiteCollectionService $collectionService,
        private ?SiteEntryService $entryService,
        private ?SiteCategoryService $categoryService,
        private ?SiteTagService $tagService,
        private int $collectionId,
        private Closure $urlBuilder,
        private Closure $mediaNormalizer,
    ) {
    }

    public function fetch(ListingQuery $query, string $lang): ListingResult
    {
        if ($this->entryService === null) {
            return new ListingResult();
        }

        $collection = $this->resolveCollection($lang);
        if ($collection === null) {
            return new ListingResult();
        }

        $apiQuery = [
            'page' => $query->page,
            'per_page' => $query->perPage,
            'order_by' => $query->orderBy,
            'order_direction' => $query->orderDirection,
            // `documents` is normalized into prepareEntries()'s output but
            // never actually read back out by collection_listing.php's
            // template (grepped: no `listingContent['documents']`/
            // `listing_content.documents` access in the view) — every other
            // sub-key IS consumed (image/secondary_action/rich_text/video
            // gated by per-instance show_extra_* toggles this shared source
            // doesn't have access to; publication_date feeds normalizeEntry()'s
            // display_date fallback; date_fields/fields back the flexible
            // slot-projection system), so only `documents` is dropped here —
            // conservative on purpose given how easy this file is to get
            // subtly wrong (see git history of this comment). See
            // docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.C.
            'include' => 'listing_content.image,listing_content.secondary_action,listing_content.rich_text,listing_content.video,listing_content.publication_date,listing_content.date_fields,listing_content.fields',
        ];
        if ($query->orderBy !== '' && (str_starts_with($query->orderBy, 'entry.') || str_starts_with($query->orderBy, 'block.') || str_starts_with($query->orderBy, 'taxonomy.'))) {
            $apiQuery['order_by'] = 'field:' . $query->orderBy;
        }
        // listing_projection sources are logical paths consumed from the
        // returned entry/listing_content envelope. They are not CMS API field
        // names; the public-read endpoint only accepts its fixed field list.
        $apiQuery['fields'] = 'id,slug,title,excerpt,published_at,featured_image,listing_content';
        if ($query->category !== '') {
            $apiQuery['category'] = $query->category;
        }
        if ($query->categoryId > 0) {
            $apiQuery['category_id'] = $query->categoryId;
        }
        if ($query->tag !== '') {
            $apiQuery['tag'] = $query->tag;
        }
        if ($query->query !== '') {
            $apiQuery['q'] = $query->query;
        }
        if ($query->filterBy !== '' && $query->filterValue !== '') {
            $apiQuery['filter_by'] = $query->filterBy;
            $apiQuery['filter_value'] = $query->filterValue;
            $apiQuery['filter_operator'] = $query->filterOperator;
        }

        try {
            $result = $this->entryService->list($lang, (string) ($collection['collection_key'] ?? ''), $apiQuery);
            return new ListingResult($result['data'] ?? [], $result['meta']['pagination'] ?? []);
        } catch (\Throwable) {
            return new ListingResult();
        }
    }

    public function facets(ListingQuery $query, string $lang): array
    {
        if ($this->categoryService === null || $this->tagService === null) {
            return [];
        }

        $collection = $this->resolveCollection($lang);
        if ($collection === null) {
            return [];
        }

        $collectionKey = (string) ($collection['collection_key'] ?? '');
        $categories = [];
        $tags = [];

        try {
            $categoriesData = $this->categoryService->list($lang, $collectionKey);
            foreach ($categoriesData as $category) {
                $slug = trim((string) ($category['slug'] ?? ''), '/');
                if ($slug === '') {
                    continue;
                }

                $categoryQuery = clone $query;
                $categoryQuery->category = $slug;
                $categoryQuery->tag = '';
                $categoryQuery->page = 1;

                $category['url'] = ($this->urlBuilder)($categoryQuery);
                $categories[] = $category;
            }
        } catch (\Throwable) {
        }

        try {
            $tagsData = $this->tagService->list($lang, $collectionKey);
            foreach ($tagsData as $tag) {
                $slug = trim((string) ($tag['slug'] ?? ''), '/');
                if ($slug === '') {
                    continue;
                }

                $tagQuery = clone $query;
                $tagQuery->tag = $slug;
                $tagQuery->category = '';
                $tagQuery->page = 1;

                $tag['url'] = ($this->urlBuilder)($tagQuery);
                $tags[] = $tag;
            }
        } catch (\Throwable) {
        }

        return ['categories' => $categories, 'tags' => $tags];
    }

    public function normalizeEntry(array $entry): array
    {
        // CMS entries are already mostly in the correct format from the API
        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $entry['title'] = (string) ($localized['title'] ?? $entry['title'] ?? '');
        $entry['slug'] = (string) ($localized['slug'] ?? $entry['slug'] ?? '');
        // Public CMS entries expose the migrated course description as `excerpt`.
        // Keep `summary` as a fallback for older/custom collection payloads.
        $entry['excerpt'] = (string) ($entry['excerpt'] ?? $entry['summary'] ?? '');
        $entry['published_at'] = (string) ($entry['published_at'] ?? '');
        $listingContent = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
        $entry['display_date'] = $this->firstNonEmpty([
            $listingContent['publication_date'] ?? null,
            $entry['published_at'],
            $entry['created_at'] ?? null,
        ]);
        $entry['categories'] = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        $entry['tags'] = is_array($entry['tags'] ?? null) ? $entry['tags'] : [];

        $featuredImage = $entry['cover_image']
            ?? $entry['featured_image']
            ?? $entry['main_image']
            ?? $listingContent['image']
            ?? $this->legacyFeaturedImage($entry, $listingContent);
        if (is_array($featuredImage) || is_string($featuredImage)) {
            $entry['featured_image'] = ($this->mediaNormalizer)($featuredImage);
        } else {
            $entry['featured_image'] = null;
        }

        return $entry;
    }

    /**
     * Reconstruct the canonical media reference used by older CMS listing
     * responses. The frontend may use the URL only when one is actually
     * supplied; a file ID alone remains unresolved instead of becoming a
     * guessed private file route.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $listingContent
     * @return array{source_kind: string, file_id: int|null, url: string}|null
     */
    private function legacyFeaturedImage(array $entry, array $listingContent): ?array
    {
        $fileId = $entry['featured_file_id']
            ?? $entry['featured_image_file_id']
            ?? $listingContent['image_file_id']
            ?? null;
        $url = $entry['featured_image_url']
            ?? $entry['featured_url']
            ?? $listingContent['image_url']
            ?? null;

        if ($fileId === null && (! is_string($url) || trim($url) === '')) {
            return null;
        }

        return [
            'source_kind' => is_numeric($fileId) && (int) $fileId > 0 ? 'hub_file' : 'external_url',
            'file_id'     => is_numeric($fileId) && (int) $fileId > 0 ? (int) $fileId : null,
            'url'         => is_scalar($url) ? trim((string) $url) : '',
        ];
    }

    /** @param list<mixed> $values */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeDateValue($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizeDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            foreach (['value', 'date', 'datetime', 'display_date'] as $key) {
                if (array_key_exists($key, $value)) {
                    $normalized = $this->normalizeDateValue($value[$key]);
                    if ($normalized !== '') {
                        return $normalized;
                    }
                }
            }

            foreach ($value as $nestedValue) {
                $normalized = $this->normalizeDateValue($nestedValue);
                if ($normalized !== '') {
                    return $normalized;
                }
            }

            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'published_at',
            'order_direction' => 'desc',
            'page_title' => lang('Site.collection_index_label'),
            'intro_text' => '',
            'section_label' => lang('Site.collection_index_label'),
            'item_label' => lang('Site.collection_listing_item'),
            'featured_item_label' => lang('Site.collection_listing_featured'),
            'count_label' => lang('Site.collection_listing_count'),
            'entry_cta_label' => lang('Site.view_more'),
            'show_categories' => true,
            'show_tags' => false,
            'show_date' => true,
        ];
    }

    public function previewResult(): ListingResult
    {
        $data = [
            [
                'id' => 1,
                'slug' => 'mock-entry-1',
                'title' => 'Caso de Éxito de Ejemplo 1',
                'summary' => 'Esta es una descripción corta para la primera entrada de ejemplo en la lista.',
                'published_at' => date('Y-m-d H:i:s'),
                'featured_image' => ($this->mediaNormalizer)(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80']),
                'categories' => [['title' => 'Casos', 'slug' => 'casos']],
                'tags' => [['title' => 'Tag 1', 'slug' => 'tag-1']],
            ],
            [
                'id' => 2,
                'slug' => 'mock-entry-2',
                'title' => 'Lanzamiento de Producto Especial',
                'summary' => 'Esta es una descripción corta para la segunda entrada de ejemplo en la lista.',
                'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'featured_image' => ($this->mediaNormalizer)(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80']),
                'categories' => [['title' => 'Productos', 'slug' => 'productos']],
                'tags' => [['title' => 'Tag 2', 'slug' => 'tag-2']],
            ]
        ];

        return new ListingResult($data, [
            'current_page' => 1,
            'total_pages' => 1,
            'per_page' => 12,
            'total_items' => 2,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCollectionData(string $lang, bool $isPreview): ?array
    {
        $collection = $this->resolveCollection($lang);
        if ($collection === null && $isPreview) {
            $collection = [
                'id' => 999,
                'name' => 'Colección de Ejemplo',
                'collection_key' => 'mock-collection',
                'slug' => 'mock-collection',
                'listing_title' => 'Colección de Ejemplo en Vista Previa',
                'default_meta_description' => 'Descripción de ejemplo para metadatos de la colección.',
            ];
        }
        return $collection;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCollection(string $lang): ?array
    {
        if ($this->collectionService === null) {
            return null;
        }

        if ($this->collection !== null) {
            return $this->collection;
        }

        if ($this->collectionId <= 0) {
            return null;
        }

        try {
            $collections = $this->collectionService->getAll($lang);
            foreach ($collections as $c) {
                if ((int) ($c['id'] ?? 0) === $this->collectionId) {
                    $this->collection = $c;
                    return $c;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
