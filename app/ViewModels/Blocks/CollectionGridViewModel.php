<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteCatalogService;
use App\Services\SiteEntryService;
use App\Services\SiteEventService;
use App\ViewModels\Blocks\Listing\ListingQuery;
use App\ViewModels\Blocks\Listing\Sources\CatalogItemsSource;
use App\ViewModels\Blocks\Listing\Sources\EventItemsSource;

class CollectionGridViewModel extends AbstractBlockViewModel
{
    private const ORDER_COLUMNS   = ['published_at', 'sort_order', 'created_at', 'title'];
    private const LAYOUT_VARIANTS = ['cards', 'compact', 'portfolio'];
    private const IMAGE_ASPECT_RATIOS = ['16/9', '4/3', '1/1', '3/4', '2/3'];
    private const SOURCE_TYPES = ['auto', 'cms_collection', 'catalog_items', 'event_items'];

    public function vars(): array
    {
        $collectionKey = $this->configString('collection_key');
        $categoryId = max(0, $this->configInt('category_id', 0));
        $sourceType = $this->resolveSourceType($collectionKey);
        $itemsLimit    = max(1, min(100, $this->configInt('items_limit', 3)));

        $orderBy = $this->configString('order_by');
        if (! in_array($orderBy, self::ORDER_COLUMNS, true)) {
            $orderBy = 'published_at';
        }

        $orderDirection = strtolower($this->configString('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $layoutVariant = $this->configString('layout_variant');
        if (! in_array($layoutVariant, self::LAYOUT_VARIANTS, true)) {
            $layoutVariant = 'cards';
        }

        $imageAspectRatio = $this->resolveImageAspectRatio(
            $this->configString('image_aspect_ratio'),
            $collectionKey,
        );

        $navigation = is_array($this->block['navigation'] ?? null)
            ? $this->block['navigation']
            : [];
        $canonicalViewAllUrl = $this->navigationUrl($navigation);

        return [
            'sectionTitle'        => $this->dataString('section_title'),
            'sectionSubtitle'     => $this->dataString('section_subtitle'),
            'viewAllLabel'        => (string) ($navigation['label'] ?? $this->dataString('view_all_label')),
            'emptyMessage'        => $this->dataString('empty_message'),
            'collectionKey'       => $collectionKey,
            'layoutVariant'       => $layoutVariant,
            'imageAspectRatio'    => $imageAspectRatio,
            'imageAspectRatioClass' => self::aspectRatioClass($imageAspectRatio),
            'cssClass'            => $this->configString('css_class'),
            'canonicalViewAllUrl' => $canonicalViewAllUrl,
            'entries'             => array_map(
                fn (array $entry): array => $this->withEntryNavigation($entry, $canonicalViewAllUrl),
                $this->resolvePreviewEntries($collectionKey, $sourceType, $itemsLimit, $orderBy, $orderDirection, $categoryId),
            ),
            'sectionClass'        => $layoutVariant === 'portfolio' ? 'section-lg bg-slate-50/50' : 'section',
            'containerClass'      => $layoutVariant === 'portfolio' ? 'max-w-6xl mx-auto px-4' : 'container-base',
            'gridClass'           => match ($layoutVariant) {
                'compact'   => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
                'portfolio' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-3',
                default     => 'grid gap-6 md:grid-cols-3',
            },
        ];
    }

    public static function aspectRatioClass(string $ratio): string
    {
        return match ($ratio) {
            '16/9' => 'aspect-video',
            '4/3'  => 'aspect-[4/3]',
            '1/1'  => 'aspect-square',
            '3/4'  => 'aspect-[3/4]',
            '2/3'  => 'aspect-[2/3]',
            default => 'aspect-[4/3]',
        };
    }

    private function resolveImageAspectRatio(string $ratio, string $collectionKey): string
    {
        $ratio = trim($ratio);

        if (in_array($ratio, self::IMAGE_ASPECT_RATIOS, true)) {
            return $ratio;
        }

        $defaultRatio = match (strtolower(trim($collectionKey))) {
            'cartelera', 'events', 'eventos', 'obras', 'works', 'noticias', 'news', 'editoriales', 'publicaciones', 'publications', 'prensa', 'press', 'transparencia', 'transparency', 'festivales', 'festivals', 'companias', 'companies' => '1/1',
            'personas', 'people' => '3/4',
            'exposiciones', 'exhibitions' => '2/3',
            'cursos' => '3/4',
            'videos', 'video', 'multimedia' => '16/9',
            default => '4/3',
        };

        return $defaultRatio;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(string $collectionKey, int $itemsLimit, string $orderBy, string $orderDirection, int $categoryId = 0): array
    {
        $service = $this->contextService('siteEntryService', SiteEntryService::class);
        if ($service === null) {
            return [];
        }

        try {
            $query = [
                'per_page'        => $itemsLimit,
                'order_by'        => $orderBy,
                'order_direction' => $orderDirection,
            ];
            if ($categoryId > 0) {
                $query['category_id'] = $categoryId;
            }
            $result = $service->list($this->lang, $collectionKey, $query);

            $entries = $result['data'] ?? [];
            if (! is_array($entries)) {
                return [];
            }

            $normalized = [];
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $featuredImage = $this->mediaReferenceFromPayload($entry, 'featured_image');
                $entry['featured_image'] = $featuredImage['url'] !== '' ? $featuredImage : null;

                $normalized[] = $entry;
            }

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve entries for preview mode, falling back to mock entries if empty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolvePreviewEntries(string $collectionKey, string $sourceType, int $itemsLimit, string $orderBy, string $orderDirection, int $categoryId = 0): array
    {
        if ($sourceType === 'event_items') {
            return $this->externalEntries('event_items', $itemsLimit, $orderBy, $orderDirection);
        }

        if ($sourceType === 'catalog_items') {
            return $this->externalEntries('catalog_items', $itemsLimit, $orderBy, $orderDirection);
        }

        $entries = [];
        if ($collectionKey !== '') {
            $entries = $this->entries($collectionKey, $itemsLimit, $orderBy, $orderDirection, $categoryId);
        }

        if ($entries === [] && $this->isPreviewRequest()) {
            return [
                [
                    'id' => 1,
                    'slug' => 'mock-entry-1',
                    'title' => 'Caso de Éxito de Ejemplo',
                    'summary' => 'Resumen breve de la historia de éxito que ilustra la efectividad de nuestra metodología en proyectos reales.',
                    'published_at' => date('Y-m-d H:i:s'),
                    'featured_image' => $this->normalizeMediaReference(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80']),
                    'categories' => [['title' => 'Casos de Éxito', 'slug' => 'casos']],
                    'tags' => [['title' => 'Negocios', 'slug' => 'negocios']],
                ],
                [
                    'id' => 2,
                    'slug' => 'mock-entry-2',
                    'title' => 'Lanzamiento de Nueva Solución',
                    'summary' => 'Una descripción detallada de las ventajas y el impacto de nuestra última innovación tecnológica en el sector.',
                    'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'featured_image' => $this->normalizeMediaReference(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80']),
                    'categories' => [['title' => 'Innovación', 'slug' => 'innovacion']],
                    'tags' => [['title' => 'Tecnología', 'slug' => 'tecnologia']],
                ],
                [
                    'id' => 3,
                    'slug' => 'mock-entry-3',
                    'title' => 'Mejores Prácticas en el Sector',
                    'summary' => 'Una guía completa de las tendencias actuales y recomendaciones clave para optimizar procesos y flujos de trabajo.',
                    'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'featured_image' => $this->normalizeMediaReference(['source_kind' => 'external_url', 'file_id' => null, 'url' => 'https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?auto=format&fit=crop&w=600&q=80']),
                    'categories' => [['title' => 'TeatroEscuela', 'slug' => 'teatroescuela']],
                    'tags' => [['title' => 'Guías', 'slug' => 'guias']],
                ]
            ];
        }

        return $entries;
    }

    /**
     * Cartelera is owned by the event domain, not by the CMS collections table.
     * Keep the collection_grid presentation while routing this special source
     * through the same event normalizer used by the full cartelera listing.
     *
     * @return list<array<string, mixed>>
     */
    private function externalEntries(string $sourceType, int $itemsLimit, string $orderBy, string $orderDirection): array
    {
        $source = match ($sourceType) {
            'event_items' => $this->eventSource(),
            'catalog_items' => $this->catalogSource(),
            default => null,
        };

        if ($source === null) {
            return [];
        }

        try {
            $sort = $sourceType === 'event_items' ? 'start_time' : ($orderBy !== '' ? $orderBy : 'name');
            $direction = $sourceType === 'event_items' ? 'asc' : $orderDirection;
            $result = $source->fetch(new ListingQuery(1, $itemsLimit, '', 0, '', '', $sort, $direction), $this->lang);

            return array_map(
                fn (array $entry): array => $source->normalizeEntry($entry),
                $result->data,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function eventSource(): ?EventItemsSource
    {
        $service = $this->contextService('siteEventService', SiteEventService::class);
        if ($service === null) {
            return null;
        }

        return new EventItemsSource(
            $service,
            static fn (ListingQuery $query): string => '',
            fn (array $media): array => $this->normalizeMediaReference($media),
        );
    }

    private function catalogSource(): ?CatalogItemsSource
    {
        $service = $this->contextService('siteCatalogService', SiteCatalogService::class);
        if ($service === null) {
            return null;
        }

        return new CatalogItemsSource(
            $service,
            static fn (ListingQuery $query): string => '',
            fn (array $media): array => $this->normalizeMediaReference($media),
        );
    }

    private function resolveSourceType(string $collectionKey): string
    {
        $configured = strtolower(trim($this->configString('source_type', 'auto')));
        if ($configured !== 'auto' && in_array($configured, self::SOURCE_TYPES, true)) {
            return $configured;
        }

        return match (strtolower(trim($collectionKey))) {
            'cartelera', 'events', 'eventos' => 'event_items',
            'museo', 'catalogo', 'catalog', 'fichas', 'collection_items' => 'catalog_items',
            default => 'cms_collection',
        };
    }
}
