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
        private SiteCollectionService $collectionService,
        private SiteEntryService $entryService,
        private SiteCategoryService $categoryService,
        private SiteTagService $tagService,
        private int $collectionId,
        private Closure $urlBuilder,
        private Closure $mediaNormalizer
    ) {
    }

    public function fetch(ListingQuery $query, string $lang): ListingResult
    {
        $collection = $this->resolveCollection($lang);
        if ($collection === null) {
            return new ListingResult();
        }

        $apiQuery = [
            'page' => $query->page,
            'per_page' => $query->perPage,
            'order_by' => $query->orderBy,
            'order_direction' => $query->orderDirection,
            'include' => 'listing_content',
        ];
        if ($query->category !== '') {
            $apiQuery['category'] = $query->category;
        }
        if ($query->tag !== '') {
            $apiQuery['tag'] = $query->tag;
        }
        if ($query->query !== '') {
            $apiQuery['q'] = $query->query;
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
        $collection = $this->resolveCollection($lang);
        if ($collection === null) {
            return [];
        }

        $collectionKey = (string) ($collection['collection_key'] ?? '');
        $categories = [];
        $tags = [];

        try {
            $categoriesData = $this->categoryService->listCategories($lang, $collectionKey);
            if (is_array($categoriesData)) {
                foreach ($categoriesData as $category) {
                    if (! is_array($category)) {
                        continue;
                    }
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
            }
        } catch (\Throwable) {
        }

        try {
            $tagsData = $this->tagService->listTags($lang, $collectionKey);
            if (is_array($tagsData)) {
                foreach ($tagsData as $tag) {
                    if (! is_array($tag)) {
                        continue;
                    }
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
            }
        } catch (\Throwable) {
        }

        return ['categories' => $categories, 'tags' => $tags];
    }

    public function normalizeEntry(array $entry): array
    {
        // CMS entries are already mostly in the correct format from the API
        $entry['title'] = (string) ($entry['title'] ?? '');
        $entry['slug'] = (string) ($entry['slug'] ?? '');
        $entry['excerpt'] = (string) ($entry['summary'] ?? '');
        $entry['published_at'] = (string) ($entry['published_at'] ?? '');
        $entry['categories'] = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        $entry['tags'] = is_array($entry['tags'] ?? null) ? $entry['tags'] : [];

        $featuredImage = $entry['cover_image'] ?? $entry['featured_image'] ?? $entry['main_image'] ?? null;
        if (is_array($featuredImage)) {
            $entry['featured_image'] = ($this->mediaNormalizer)($featuredImage);
        } else {
            $entry['featured_image'] = null;
        }

        return $entry;
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'published_at',
            'order_direction' => 'desc',
            'source_path' => '',
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

    private function resolveCollection(string $lang): ?array
    {
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
