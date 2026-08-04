<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteCatalogService;
use App\Services\SiteCategoryService;
use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;
use App\Services\SiteEventService;
use App\Services\SiteTagService;
use App\ViewModels\Blocks\Listing\ListingQuery;
use App\ViewModels\Blocks\Listing\ListingSourceInterface;
use App\ViewModels\Blocks\Listing\Sources\CatalogItemsSource;
use App\ViewModels\Blocks\Listing\Sources\CmsCollectionSource;
use App\ViewModels\Blocks\Listing\Sources\EventItemsSource;

class CollectionListingViewModel extends AbstractBlockViewModel
{
    private const LAYOUT_VARIANTS = ['cards', 'compact', 'portfolio', 'list'];

    public function vars(): array
    {
        $sourceType = $this->resolveSourceType();
        $layoutVariant = $this->resolveLayoutVariant($this->configString('layout_variant', 'cards'));
        $source = $this->resolveSource($sourceType);

        if ($source === null) {
            return $this->emptyVars($this->fallbackDefaults(), $layoutVariant);
        }

        $defaults = $source->defaults();

        $currentPage = max(1, (int) ($this->requestGet('page') ?: 1));
        $currentCategory = trim($this->requestGet('category'));
        $currentTag = trim($this->requestGet('tag'));
        $currentQuery = trim($this->requestGet('q'));
        $configuredCategoryId = max(0, $this->configInt('category_id', 0));

        $orderBy = $this->resolveOrderBy($this->requestGet('order_by'), $defaults['order_by']);
        $orderDirection = $this->resolveOrderDirection($this->requestGet('order_direction'), $defaults['order_direction']);
        $perPage = max(1, min(100, $this->configInt('per_page', 12)));

        $query = new ListingQuery(
            page: $currentPage,
            perPage: $perPage,
            category: $currentCategory,
            categoryId: $configuredCategoryId,
            tag: $currentTag,
            query: $currentQuery,
            orderBy: $orderBy,
            orderDirection: $orderDirection
        );

        $result = $source->fetch($query, $this->lang);

        if ((empty($result->data) || !is_array($result->data)) && $this->isPreviewRequest()) {
            $result = $source->previewResult();
        }

        $showCategories = $this->configBool('show_categories', $defaults['show_categories']);
        $showTags = $this->configBool('show_tags', $defaults['show_tags']);
        $facets = ($showCategories || $showTags) ? $source->facets($query, $this->lang) : [];
        $categories = $showCategories ? ($facets['categories'] ?? []) : [];
        $tags = $showTags ? ($facets['tags'] ?? []) : [];

        $normalizedEntries = $this->prepareEntries(
            array_map(fn ($entry) => $source->normalizeEntry($entry), $result->data)
        );

        $pagination = $result->pagination;
        $currentPage = max(1, (int) ($pagination['current_page'] ?? $currentPage));

        $collectionKey = $sourceType;
        $navigation = is_array($this->block['navigation'] ?? null)
            ? $this->block['navigation']
            : [];
        $navigationUrl = $this->navigationUrl($navigation);
        $collectionUrlPath = $this->navigationPath($navigationUrl)
            ?: ($this->localizedDomainSourcePath($sourceType, $this->lang) ?? '')
            ?: ($sourceType === 'cms_collection' ? $this->currentRequestPath() : '');
        $localizedUrls = $this->localizedSourceUrls($sourceType, $collectionUrlPath);
        $collection = [
            'id' => 0,
            'collection_key' => $sourceType,
            'collection_type' => $sourceType,
            'slug' => $collectionUrlPath,
            'name' => $defaults['page_title'],
            'listing_title' => $this->textString('intro_title', $defaults['page_title']),
            'listing_intro' => $this->textString('intro_text', $defaults['intro_text']),
            'default_meta_description' => $this->textString('intro_text', $defaults['intro_text']),
            'entry_cta_label' => $this->textString('entry_cta_label', $defaults['entry_cta_label']),
            'section_label' => $this->textString('section_label', $defaults['section_label']),
            'item_label' => $this->textString('item_label', $defaults['item_label']),
            'featured_item_label' => $this->textString('featured_item_label', $defaults['featured_item_label']),
            'count_label' => $this->textString('count_label', $defaults['count_label']),
        ];

        if ($sourceType === 'cms_collection' && $source instanceof CmsCollectionSource) {
            $cmsCollection = $source->getCollectionData($this->lang, $this->isPreviewRequest());
            if ($cmsCollection !== null) {
                $collection = $cmsCollection;
                $collectionKey = (string) ($collection['collection_key'] ?? '');
                $collectionUrlPath = $this->navigationPath($navigationUrl)
                    ?: $this->currentRequestPath();
                $localizedUrls = [];
            } else {
                return $this->emptyVars($defaults, $layoutVariant);
            }
        }

        $entryListingUrl = $navigationUrl;
        if ($entryListingUrl === '' && $collectionUrlPath !== '') {
            $entryListingUrl = lang_url($collectionUrlPath, $this->lang);
        }
        $normalizedEntries = array_map(
            fn (array $entry): array => $this->withEntryNavigation($entry, $entryListingUrl),
            $normalizedEntries,
        );

        $displayTitle = collection_display_title($collection);
        $displayIntro = collection_display_intro($collection);

        return [
            'isValid' => true,
            'collection' => $collection,
            'collectionUrlPath' => $collectionUrlPath,
            'localizedUrls' => $localizedUrls,
            'collectionKey' => $collectionKey,
            'entries' => $normalizedEntries,
            'navigation' => $navigation,
            'viewAllLabel' => (string) ($navigation['label'] ?? ''),
            'pagination' => $pagination,
            'currentPage' => $currentPage,
            'currentCategory' => $currentCategory,
            'currentTag' => $currentTag,
            'currentQuery' => $currentQuery,
            'orderBy' => $orderBy,
            'orderDirection' => $orderDirection,
            'layoutVariant' => $layoutVariant,
            'imageAspectRatio' => $this->configString('image_aspect_ratio', '16/9'),
            'cssClass' => $this->configString('css_class'),
            'showSearch' => $this->configBool('show_search', true),
            'showCategories' => $showCategories,
            'showTags' => $showTags,
            'showExcerpt' => $this->configBool('show_excerpt', true),
            'showDate' => $this->configBool('show_date', $defaults['show_date']),
            'showButton' => $this->configBool('show_button', true),
            'showItemCategories' => $this->configBool('show_item_categories', true),
            'showExtraRichtext' => $this->configBool('show_extra_richtext', false),
            'showExtraLink' => $this->configBool('show_extra_link', false),
            'showExtraImage' => $this->configBool('show_extra_image', false),
            'emptyMessage' => $this->textString('empty_message', $this->defaultEmptyMessage()),
            'introTitle' => $this->textString('intro_title', $defaults['page_title']),
            'introText' => $this->textString('intro_text', $defaults['intro_text']),
            'sectionLabel' => $collection['section_label'] ?? $this->textString('section_label', $defaults['section_label']),
            'itemLabel' => $collection['item_label'] ?? $this->textString('item_label', $defaults['item_label']),
            'featuredItemLabel' => $collection['featured_item_label'] ?? $this->textString('featured_item_label', $defaults['featured_item_label']),
            'countLabel' => $collection['count_label'] ?? $this->textString('count_label', $defaults['count_label']),
            'categories' => $categories,
            'tags' => $tags,
            'pageTitle' => $displayTitle !== ''
                ? $displayTitle
                : $defaults['page_title'],
            'metaDescription' => $this->resolveCollectionMetaDescription($collection, $displayIntro, $defaults['intro_text']),
            // No fallback to a configured/admin-authored placeholder here on purpose: showing
            // the same generic stock photo on every card without a real cover was misleading.
            // The card view already hides the image container entirely when it's empty.
            'fallbackImageUrl' => '',
        ];
    }

    /**
     * SEO description takes priority over the on-page intro text: a collection
     * may have a curated `default_meta_description` distinct from what's
     * displayed above the listing (`listing_intro`/`description`).
     *
     * @param array<string, mixed> $collection
     */
    private function resolveCollectionMetaDescription(array $collection, string $displayIntro, string $fallback): string
    {
        $metaDescription = trim((string) ($collection['default_meta_description'] ?? ''));
        if ($metaDescription !== '') {
            return $metaDescription;
        }

        return $displayIntro !== '' ? $displayIntro : $fallback;
    }

    private function resolveSource(string $sourceType): ?ListingSourceInterface
    {
        $urlBuilder = fn (ListingQuery $query) => $this->buildUrl([
            'category' => $query->category ?: null,
            'tag' => $query->tag ?: null,
            'q' => $query->query ?: null,
            'order_by' => $query->orderBy ?: null,
            'order_direction' => $query->orderDirection ?: null,
            'page' => $query->page > 1 ? $query->page : null,
        ]);

        $mediaNormalizer = fn (array $media) => $this->normalizeMediaReference($media);

        return match ($sourceType) {
            'catalog_items' => $this->resolveCatalogItemsSource($urlBuilder, $mediaNormalizer),
            'event_items' => $this->resolveEventItemsSource($urlBuilder, $mediaNormalizer),
            default => $this->resolveCmsCollectionSource($urlBuilder, $mediaNormalizer),
        };
    }

    private function resolveCatalogItemsSource(\Closure $urlBuilder, \Closure $mediaNormalizer): ?CatalogItemsSource
    {
        $catalogService = $this->contextService('siteCatalogService', SiteCatalogService::class);

        return $catalogService !== null
            ? new CatalogItemsSource($catalogService, $urlBuilder, $mediaNormalizer)
            : null;
    }

    private function resolveEventItemsSource(\Closure $urlBuilder, \Closure $mediaNormalizer): ?EventItemsSource
    {
        $eventService = $this->contextService('siteEventService', SiteEventService::class);

        return $eventService !== null
            ? new EventItemsSource($eventService, $urlBuilder, $mediaNormalizer)
            : null;
    }

    private function resolveCmsCollectionSource(\Closure $urlBuilder, \Closure $mediaNormalizer): ?CmsCollectionSource
    {
        $collectionService = $this->contextService('siteCollectionService', SiteCollectionService::class);
        $entryService = $this->contextService('siteEntryService', SiteEntryService::class);
        $categoryService = $this->contextService('siteCategoryService', SiteCategoryService::class);
        $tagService = $this->contextService('siteTagService', SiteTagService::class);

        if ($collectionService === null || $entryService === null || $categoryService === null || $tagService === null) {
            return null;
        }

        return new CmsCollectionSource(
            $collectionService,
            $entryService,
            $categoryService,
            $tagService,
            $this->configInt('collection_id', 0),
            $urlBuilder,
            $mediaNormalizer
        );
    }

    /**
     * Generic listing defaults used only when the required context
     * collaborators are unavailable (e.g. a view model built without going
     * through BlockRenderer) and no real source could be resolved.
     *
     * @return array<string, mixed>
     */
    private function fallbackDefaults(): array
    {
        return [
            'order_by' => 'published_at',
            'order_direction' => 'desc',
            'show_categories' => true,
            'show_tags' => false,
            'show_date' => true,
            'page_title' => lang('Site.collection_index_label'),
            'intro_text' => '',
            'section_label' => lang('Site.collection_index_label'),
            'item_label' => lang('Site.collection_listing_item'),
            'featured_item_label' => lang('Site.collection_listing_featured'),
            'count_label' => lang('Site.collection_listing_count'),
            'fallback_image_url' => '',
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function emptyVars(array $defaults, string $layoutVariant): array
    {
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
            'orderBy' => $defaults['order_by'],
            'orderDirection' => $defaults['order_direction'],
            'layoutVariant' => $layoutVariant,
            'imageAspectRatio' => $this->configString('image_aspect_ratio', '16/9'),
            'cssClass' => $this->configString('css_class'),
            'showSearch' => $this->configBool('show_search', true),
            'showCategories' => $this->configBool('show_categories', $defaults['show_categories']),
            'showTags' => $this->configBool('show_tags', $defaults['show_tags']),
            'showExcerpt' => $this->configBool('show_excerpt', true),
            'showDate' => $this->configBool('show_date', $defaults['show_date']),
            'showButton' => $this->configBool('show_button', true),
            'showItemCategories' => $this->configBool('show_item_categories', true),
            'showExtraRichtext' => $this->configBool('show_extra_richtext', false),
            'showExtraLink' => $this->configBool('show_extra_link', false),
            'showExtraImage' => $this->configBool('show_extra_image', false),
            'emptyMessage' => $this->textString('empty_message', $this->defaultEmptyMessage()),
            'introTitle' => $this->textString('intro_title', $defaults['page_title']),
            'introText' => $this->textString('intro_text', $defaults['intro_text']),
            'sectionLabel' => $this->textString('section_label', $defaults['section_label']),
            'itemLabel' => $this->textString('item_label', $defaults['item_label']),
            'featuredItemLabel' => $this->textString('featured_item_label', $defaults['featured_item_label']),
            'countLabel' => $this->textString('count_label', $defaults['count_label']),
            'collection' => null,
            'collectionUrlPath' => '',
            'localizedUrls' => [],
            'collectionKey' => '',
            'navigation' => [],
            'viewAllLabel' => '',
            'pageTitle' => $defaults['page_title'],
            'metaDescription' => $defaults['intro_text'],
        ];
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

    private function resolveSourceType(): string
    {
        $value = strtolower(trim($this->configString('source_type', 'cms_collection')));

        return in_array($value, ['cms_collection', 'catalog_items', 'event_items'], true)
            ? $value
            : 'cms_collection';
    }

    private function resolveOrderBy(string $requestValue, string $default): string
    {
        $value = strtolower(trim($requestValue));
        return in_array($value, ['published_at', 'sort_order', 'created_at', 'title', 'name', 'start_time'], true)
            ? $value
            : $default;
    }

    private function resolveOrderDirection(string $requestValue, string $default): string
    {
        $value = strtolower(trim($requestValue));
        return in_array($value, ['asc', 'desc'], true) ? $value : $default;
    }

    private function resolveLayoutVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));
        return in_array($variant, self::LAYOUT_VARIANTS, true) ? $variant : 'cards';
    }

    private function textString(string $key, string $default = ''): string
    {
        $value = trim($this->dataString($key, ''));
        if ($value !== '') {
            return $value;
        }

        $value = trim($this->configString($key, ''));

        return $value !== '' ? $value : $default;
    }

    private function defaultEmptyMessage(): string
    {
        return lang('Site.collection_listing_empty') ?: 'No se encontraron resultados que coincidan con la búsqueda.';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildUrl(array $params): string
    {
        $request = $this->contextRequest();
        $currentUrl = $request !== null ? (string) $request->getUri()->setQuery('') : '';
        $queryStr = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''));

        if ($queryStr === '') {
            return $currentUrl;
        }

        return $currentUrl . '?' . $queryStr;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function prepareEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $content = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
            $image = is_array($content['image'] ?? null) ? $content['image'] : null;
            $action = is_array($content['secondary_action'] ?? null) ? $content['secondary_action'] : null;
            $richText = is_string($content['rich_text'] ?? null) ? trim($content['rich_text']) : '';

            $entry['listing_content'] = [
                'rich_text' => $richText !== '' ? \App\Libraries\HtmlSanitizer::clean($richText) : '',
                'image' => $this->normalizeListingImage($image),
                'secondary_action' => $this->normalizeListingAction($action),
            ];
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $image
     * @return array<string, mixed>|null
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
     * @return array<string, mixed>|null
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
     * @return array<string, string>
     */
    private function localizedSourceUrls(string $sourceType, string $fallbackPath): array
    {
        $urls = [];
        foreach (config('App')->supportedLocales as $locale) {
            $urls[$locale] = site_url('/' . $locale . '/' . ($this->localizedDomainSourcePath($sourceType, $locale) ?? $fallbackPath));
        }
        return $urls;
    }

    /**
     * The `catalog_items`/`event_items` sources are backed by dedicated
     * domain apps (not CMS-managed collections), so their public path
     * segment is locale-aware per `PublicPaths` rather than coming from
     * `ListingSourceInterface::defaults()`, which has no locale context.
     * Returns null for any other source type (e.g. `cms_collection`, which
     * resolves its own URL via `resolvedCollectionUrlPath()` above).
     */
    private function localizedDomainSourcePath(string $sourceType, string $locale): ?string
    {
        return match ($sourceType) {
            'catalog_items' => \App\Support\PublicPaths::catalogSegment($locale),
            'event_items' => \App\Support\PublicPaths::eventsSegment($locale),
            default => null,
        };
    }

    private function navigationPath(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        if (($segments[0] ?? '') === trim($this->lang, '/')) {
            array_shift($segments);
        }

        return trim(implode('/', $segments), '/');
    }

    /**
     * Resolve the current localized page path without its locale prefix or
     * query string. A CMS listing page remains navigable when older or
     * partially migrated block payloads do not include `navigation.url`.
     */
    private function currentRequestPath(): string
    {
        $request = $this->contextRequest();
        if ($request === null) {
            return '';
        }

        $path = trim((string) $request->getUri()->getPath(), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        if (($segments[0] ?? '') === trim($this->lang, '/')) {
            array_shift($segments);
        }

        return trim(implode('/', $segments), '/');
    }
}
