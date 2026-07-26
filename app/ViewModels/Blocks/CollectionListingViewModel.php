<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteCategoryService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteTagService;

class CollectionListingViewModel extends AbstractBlockViewModel
{
    private const ORDER_COLUMNS = ['published_at', 'sort_order', 'created_at', 'title'];
    private const LAYOUT_VARIANTS = ['cards', 'compact', 'portfolio', 'list'];

    /** @var array<string, mixed>|null */
    private ?array $collection = null;

    public function vars(): array
    {
        $collectionId = $this->configInt('collection_id', 0);
        $collection = $this->resolveCollection($collectionId);

        if ($collection === null && $this->isPreviewRequest()) {
            $collection = [
                'id' => 999,
                'name' => 'Colección de Ejemplo',
                'collection_key' => 'mock-collection',
                'slug' => 'mock-collection',
                'listing_title' => 'Colección de Ejemplo en Vista Previa',
                'default_meta_description' => 'Descripción de ejemplo para metadatos de la colección.',
            ];
        }

        $this->collection = $collection;

        if ($collection === null) {
            return [
                'isValid' => false,
                'entries' => [],
                'categories' => [],
                'tags' => [],
                'pagination' => [],
                'currentPage' => 1,
                'currentCategory' => '',
                'currentTag' => '',
                'currentQuery' => '',
                'orderBy' => 'published_at',
                'orderDirection' => 'desc',
                'layoutVariant' => 'cards',
                'cssClass' => $this->configString('css_class'),
                'showSearch' => $this->configBool('show_search', true),
                'showCategories' => $this->configBool('show_categories', true),
                'showTags' => $this->configBool('show_tags', false),
                'showExcerpt' => $this->configBool('show_excerpt', true),
                'showDate' => $this->configBool('show_date', true),
                'showButton' => $this->configBool('show_button', true),
                'showItemCategories' => $this->configBool('show_item_categories', true),
                'showExtraRichtext' => $this->configBool('show_extra_richtext', false),
                'showExtraLink' => $this->configBool('show_extra_link', false),
                'showExtraImage' => $this->configBool('show_extra_image', false),
                'emptyMessage' => $this->dataString('empty_message'),
                'introTitle' => $this->dataString('intro_title'),
                'introText' => $this->dataString('intro_text'),
                'collection' => null,
                'collectionUrlPath' => '',
                'localizedUrls' => [],
            ];
        }

        $currentPage = max(1, (int) ($this->requestGet('page') ?: 1));
        $currentCategory = trim($this->requestGet('category'));
        $currentTag = trim($this->requestGet('tag'));
        $currentQuery = trim($this->requestGet('q'));

        $orderBy = $this->resolveOrderBy($this->requestGet('order_by'));
        $orderDirection = $this->resolveOrderDirection($this->requestGet('order_direction'));
        $perPage = max(1, min(100, $this->configInt('per_page', 12)));
        $layoutVariant = $this->resolveLayoutVariant($this->configString('layout_variant', 'cards'));
        $collectionKey = (string) ($collection['collection_key'] ?? '');
        $collectionUrlPath = $this->resolvedCollectionUrlPath($collection);
        $localizedUrls = localized_collection_urls($collection);

        $query = [
            'page' => $currentPage,
            'per_page' => $perPage,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
            'include' => 'listing_content',
        ];
        if ($currentCategory !== '') {
            $query['category'] = $currentCategory;
        }
        if ($currentTag !== '') {
            $query['tag'] = $currentTag;
        }
        if ($currentQuery !== '') {
            $query['q'] = $currentQuery;
        }

        $entryService = $this->contextService('siteEntryService', SiteEntryService::class);
        $result = ['data' => [], 'meta' => ['pagination' => []]];
        if ($entryService !== null) {
            try {
                $result = $entryService->list($this->lang, $collectionKey, $query);
            } catch (\Throwable) {
                $result = ['data' => [], 'meta' => ['pagination' => []]];
            }
        }

        if ((empty($result['data']) || !is_array($result['data'])) && $this->isPreviewRequest()) {
            $result = [
                'data' => [
                    [
                        'id' => 1,
                        'slug' => 'mock-entry-1',
                        'title' => 'Caso de Éxito de Ejemplo 1',
                        'summary' => 'Esta es una descripción corta para la primera entrada de ejemplo en la lista.',
                        'published_at' => date('Y-m-d H:i:s'),
                        'featured_image' => $this->normalizeMediaReference(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80']),
                        'categories' => [['title' => 'Casos', 'slug' => 'casos']],
                        'tags' => [['title' => 'Tag 1', 'slug' => 'tag-1']],
                    ],
                    [
                        'id' => 2,
                        'slug' => 'mock-entry-2',
                        'title' => 'Lanzamiento de Producto Especial',
                        'summary' => 'Esta es una descripción corta para la segunda entrada de ejemplo en la lista.',
                        'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                        'featured_image' => $this->normalizeMediaReference(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80']),
                        'categories' => [['title' => 'Productos', 'slug' => 'productos']],
                        'tags' => [['title' => 'Tag 2', 'slug' => 'tag-2']],
                    ]
                ],
                'meta' => [
                    'pagination' => [
                        'currentPage' => 1,
                        'totalPages' => 1,
                        'perPage' => 12,
                        'totalItems' => 2,
                    ]
                ]
            ];
        }

        $categories = $this->configBool('show_categories', true)
            ? $this->resolveCategories($collectionKey, $currentPage, $currentCategory, $currentTag, $currentQuery, $orderBy, $orderDirection, $perPage)
            : [];
        $tags = $this->configBool('show_tags', false)
            ? $this->resolveTags($collectionKey, $currentPage, $currentCategory, $currentTag, $currentQuery, $orderBy, $orderDirection, $perPage)
            : [];
        $displayTitle = collection_display_title($collection);
        $displayIntro = collection_display_intro($collection);

        return [
            'isValid' => true,
            'collection' => $collection,
            'collectionUrlPath' => $collectionUrlPath,
            'localizedUrls' => $localizedUrls,
            'collectionKey' => $collectionKey,
            'entries' => $this->prepareEntries($result['data'] ?? []),
            'pagination' => is_array($result['meta']['pagination'] ?? null) ? $result['meta']['pagination'] : [],
            'currentPage' => $currentPage,
            'currentCategory' => $currentCategory,
            'currentTag' => $currentTag,
            'currentQuery' => $currentQuery,
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection,
            'layoutVariant' => $layoutVariant,
            'cssClass' => $this->configString('css_class'),
            'showSearch' => $this->configBool('show_search', true),
            'showCategories' => $this->configBool('show_categories', true),
            'showTags' => $this->configBool('show_tags', false),
            'showExcerpt' => $this->configBool('show_excerpt', true),
            'showDate' => $this->configBool('show_date', true),
            'showButton' => $this->configBool('show_button', true),
            'showItemCategories' => $this->configBool('show_item_categories', true),
            'showExtraRichtext' => $this->configBool('show_extra_richtext', false),
            'showExtraLink' => $this->configBool('show_extra_link', false),
            'showExtraImage' => $this->configBool('show_extra_image', false),
            'emptyMessage' => $this->dataString('empty_message', $this->defaultEmptyMessage()),
            'introTitle' => $this->dataString('intro_title'),
            'introText' => $this->dataString('intro_text'),
            'categories' => $categories,
            'tags' => $tags,
            'pageTitle' => $displayTitle !== ''
                ? $displayTitle
                : lang('Site.collection_index_label'),
            'metaDescription' => $displayIntro !== ''
                ? $displayIntro
                : (string) ($collection['default_meta_description'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCollection(int $collectionId): ?array
    {
        if ($collectionId <= 0) {
            return null;
        }

        $service = $this->contextService('siteCollectionService', SiteCollectionService::class);
        if ($service === null) {
            return null;
        }

        return $this->findCollection(
            $service->getAll($this->lang),
            static fn (array $collection): bool => (int) ($collection['id'] ?? 0) === $collectionId
        );
    }

    private function requestGet(string $key): string
    {
        $request = $this->contextRequest();
        if ($request === null) {
            return '';
        }

        $value = $request->getGet($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function resolveOrderBy(string $value): string
    {
        return in_array($value, self::ORDER_COLUMNS, true) ? $value : 'published_at';
    }

    private function resolveOrderDirection(string $value): string
    {
        return strtolower($value) === 'asc' ? 'asc' : 'desc';
    }

    private function resolveLayoutVariant(string $value): string
    {
        return in_array($value, self::LAYOUT_VARIANTS, true) ? $value : 'cards';
    }

    /**
     * Normalize the optional Domain projection once, keeping the template free
     * from URL policy and rich-text sanitization concerns.
     *
     * @param mixed $entries
     * @return list<array<string, mixed>>
     */
    private function prepareEntries(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $content = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
            $image = is_array($content['image'] ?? null) ? $content['image'] : null;
            $action = is_array($content['secondary_action'] ?? null) ? $content['secondary_action'] : null;
            $richText = is_string($content['rich_text'] ?? null) ? trim($content['rich_text']) : '';
            $featuredImage = $this->mediaReferenceFromPayload($entry, 'featured_image');

            $entry['listing_content'] = [
                'rich_text' => $richText !== '' ? \App\Libraries\HtmlSanitizer::clean($richText) : '',
                'image' => $this->normalizeListingImage($image),
                'secondary_action' => $this->normalizeListingAction($action),
            ];
            $entry['featured_image'] = $featuredImage['url'] !== '' ? $featuredImage : null;
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $image
     * @return array{url: string, alt: string}|null
     */
    private function normalizeListingImage(?array $image): ?array
    {
        $url = is_string($image['url'] ?? null) ? trim($image['url']) : '';
        if ($url === '') {
            return null;
        }

        return [
            'url' => $url,
            'alt' => is_string($image['alt'] ?? null) ? trim($image['alt']) : '',
        ];
    }

    /**
     * @param array<string, mixed>|null $action
     * @return array{label: string, url: string}|null
     */
    private function normalizeListingAction(?array $action): ?array
    {
        $label = is_string($action['label'] ?? null) ? trim($action['label']) : '';
        $url = is_string($action['url'] ?? null) ? trim($action['url']) : '';
        if ($label === '' || $url === '') {
            return null;
        }

        return [
            'label' => $label,
            'url' => str_starts_with($url, '/') ? lang_url($url, $this->lang) : $url,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCategories(string $collectionKey, int $currentPage, string $currentCategory, string $currentTag, string $currentQuery, string $orderBy, string $orderDirection, int $perPage): array
    {
        $service = $this->contextService('siteCategoryService', SiteCategoryService::class);
        try {
            $categories = $service?->list($this->lang, $collectionKey) ?? [];
        } catch (\Throwable) {
            $categories = [];
        }

        $filters = $this->baseFilterParams($currentPage, $currentCategory, $currentTag, $currentQuery, $orderBy, $orderDirection, $perPage);
        $result = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $slug = trim((string) ($category['slug'] ?? ''), '/');
            if ($slug === '') {
                continue;
            }

            $filters['category'] = $slug;
            $filters['tag'] = null;
            $filters['page'] = null;
            $category['url'] = $this->buildUrl($filters);
            $result[] = $category;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveTags(string $collectionKey, int $currentPage, string $currentCategory, string $currentTag, string $currentQuery, string $orderBy, string $orderDirection, int $perPage): array
    {
        $service = $this->contextService('siteTagService', SiteTagService::class);
        try {
            $tags = $service?->list($this->lang, $collectionKey) ?? [];
        } catch (\Throwable) {
            $tags = [];
        }

        $filters = $this->baseFilterParams($currentPage, $currentCategory, $currentTag, $currentQuery, $orderBy, $orderDirection, $perPage);
        $result = [];

        foreach ($tags as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $slug = trim((string) ($tag['slug'] ?? ''), '/');
            if ($slug === '') {
                continue;
            }

            $filters['tag'] = $slug;
            $filters['category'] = null;
            $filters['page'] = null;
            $tag['url'] = $this->buildUrl($filters);
            $result[] = $tag;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseFilterParams(int $page, string $category, string $tag, string $q, string $orderBy, string $orderDirection, int $perPage): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'category' => $category !== '' ? $category : null,
            'tag' => $tag !== '' ? $tag : null,
            'q' => $q !== '' ? $q : null,
            'order_by' => $orderBy !== '' ? $orderBy : null,
            'order_direction' => $orderDirection !== '' ? $orderDirection : null,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUrl(array $params): string
    {
        $path = $this->resolvedCollectionUrlPath($this->collection ?? []);
        $query = http_build_query(array_filter($params, static fn ($value) => $value !== null && $value !== ''));

        return $path !== ''
            ? lang_url($path, $this->lang) . ($query !== '' ? '?' . $query : '')
            : '#';
    }

    private function defaultEmptyMessage(): string
    {
        return lang('Site.collection_empty');
    }

    /**
     * Resolve the base path used for filter links and entry cards.
     *
     * Delegates to `localized_collection_url_path()`, the single source of
     * truth for a collection's canonical URL (index page slug, or the
     * `collection_key` fallback when there is none) — do not reintroduce a
     * page-derived fallback here; that made entry links depend on which page
     * happened to render the block (see the 2026-07-15 dead-link fix).
     *
     * @param array<string, mixed> $collection
     */
    private function resolvedCollectionUrlPath(array $collection): string
    {
        return localized_collection_url_path($collection, $this->lang);
    }
}
