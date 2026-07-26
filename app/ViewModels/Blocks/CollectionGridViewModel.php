<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteCollectionService;
use App\Services\SiteEntryService;

class CollectionGridViewModel extends AbstractBlockViewModel
{
    private const ORDER_COLUMNS   = ['published_at', 'sort_order', 'created_at', 'title'];
    private const LAYOUT_VARIANTS = ['cards', 'compact', 'portfolio'];

    public function vars(): array
    {
        $collectionKey = $this->configString('collection_key');
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

        $canonicalViewAllUrl = $collectionKey !== ''
            ? $this->canonicalViewAllUrl($collectionKey, $this->dataString('view_all_url'))
            : '';

        return [
            'sectionTitle'        => $this->dataString('section_title'),
            'sectionSubtitle'     => $this->dataString('section_subtitle'),
            'viewAllLabel'        => $this->dataString('view_all_label'),
            'emptyMessage'        => $this->dataString('empty_message'),
            'collectionKey'       => $collectionKey,
            'layoutVariant'       => $layoutVariant,
            'cssClass'            => $this->configString('css_class'),
            'canonicalViewAllUrl' => $canonicalViewAllUrl,
            'entries'             => $this->resolvePreviewEntries($collectionKey, $itemsLimit, $orderBy, $orderDirection),
            'sectionClass'        => $layoutVariant === 'portfolio' ? 'section-lg bg-slate-50/50' : 'section',
            'containerClass'      => $layoutVariant === 'portfolio' ? 'max-w-6xl mx-auto px-4' : 'container-base',
            'gridClass'           => match ($layoutVariant) {
                'compact'   => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
                'portfolio' => 'grid gap-8 sm:grid-cols-2 lg:grid-cols-3',
                default     => 'grid gap-6 md:grid-cols-3',
            },
        ];
    }

    /**
     * Canonical URL of the collection index, falling back to the manually
     * configured view_all_url when the collection isn't resolvable at all
     * (service unavailable, unknown key, or a transport error). Delegates
     * lookup + URL resolution to AbstractBlockViewModel::findCollection() and
     * the global localized_collection_url_path() — the same single source of
     * truth CollectionListingViewModel uses, so the two block types can't
     * drift out of sync on how a collection's URL is built (see the
     * 2026-07-15 dead-link fix for what that drift cost in practice).
     */
    private function canonicalViewAllUrl(string $collectionKey, string $fallback): string
    {
        $service = $this->contextService('siteCollectionService', SiteCollectionService::class);
        if ($service === null) {
            return $fallback;
        }

        try {
            $collection = $this->findCollection(
                $service->getAll($this->lang),
                static fn (array $c): bool => ($c['collection_key'] ?? '') === $collectionKey
            );
        } catch (\Throwable) {
            return $fallback;
        }

        if ($collection === null) {
            return $fallback;
        }

        $urlPath = localized_collection_url_path($collection, $this->lang);

        return $urlPath !== '' ? $urlPath : $fallback;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entries(string $collectionKey, int $itemsLimit, string $orderBy, string $orderDirection): array
    {
        $service = $this->contextService('siteEntryService', SiteEntryService::class);
        if ($service === null) {
            return [];
        }

        try {
            $result = $service->list($this->lang, $collectionKey, [
                'per_page'        => $itemsLimit,
                'order_by'        => $orderBy,
                'order_direction' => $orderDirection,
            ]);

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
    private function resolvePreviewEntries(string $collectionKey, int $itemsLimit, string $orderBy, string $orderDirection): array
    {
        $entries = [];
        if ($collectionKey !== '') {
            $entries = $this->entries($collectionKey, $itemsLimit, $orderBy, $orderDirection);
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
                    'categories' => [['title' => 'Educación', 'slug' => 'educacion']],
                    'tags' => [['title' => 'Guías', 'slug' => 'guias']],
                ]
            ];
        }

        return $entries;
    }
}
