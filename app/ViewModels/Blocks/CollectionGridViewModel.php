<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\ViewModels\Blocks\Listing\ListingDateResolver;
use App\ViewModels\Blocks\Listing\ListingVideoPresentation;
use App\ViewModels\Blocks\Listing\Sources\CatalogItemsSource;
use App\ViewModels\Blocks\Listing\Sources\EventItemsSource;

class CollectionGridViewModel extends AbstractBlockViewModel
{
    private const LAYOUT_VARIANTS = ['cards', 'compact', 'portfolio'];
    private const IMAGE_ASPECT_RATIOS = ['16/9', '4/3', '1/1', '3/4', '2/3'];
    private const SOURCE_TYPES = ['auto', 'cms_collection', 'catalog_items', 'event_items'];

    public function vars(): array
    {
        $collectionKey = $this->configString('collection_key');
        $sourceType = $this->resolveSourceType($collectionKey);
        $listingProjection = $this->listingProjection();

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
        $canonicalViewAllUrl = $this->navigationUrl($navigation)
            ?: $this->defaultListingUrl($sourceType, $collectionKey);
        $viewAllLabel = trim((string) ($navigation['label'] ?? ''));
        if ($viewAllLabel === '') {
            $viewAllLabel = trim($this->dataString('view_all_label'));
        }
        if ($viewAllLabel === '' && $canonicalViewAllUrl !== '') {
            $viewAllLabel = lang('Site.view_all');
        }

        return [
            'sectionTitle'        => $this->dataString('section_title'),
            'sectionSubtitle'     => $this->dataString('section_subtitle'),
            'viewAllLabel'        => $viewAllLabel,
            'emptyMessage'        => $this->dataString('empty_message'),
            'collectionKey'       => $collectionKey,
            'layoutVariant'       => $layoutVariant,
            'imageAspectRatio'    => $imageAspectRatio,
            'imageAspectRatioClass' => self::aspectRatioClass($imageAspectRatio),
            'cssClass'            => $this->configString('css_class'),
            'canonicalViewAllUrl' => $canonicalViewAllUrl,
            'entries'             => array_map(
                fn (array $entry): array => $this->withEntryNavigation($entry, $canonicalViewAllUrl),
                $this->resolvePrefetchedEntries($sourceType, $listingProjection),
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
            'teatroescuela' => '3/4',
            'cursos' => '3/4', // Compatibility for pre-migration payloads.
            'videos', 'video', 'multimedia' => '16/9',
            default => '4/3',
        };

        return $defaultRatio;
    }

    /**
     * Resolve only the result prepared by the BFF page envelope.
     *
     * @param array<string, mixed> $listingProjection
     * @return list<array<string, mixed>>
     */
    private function resolvePrefetchedEntries(string $sourceType, array $listingProjection): array
    {
        $prefetched = $this->prefetchedEntries($sourceType, $listingProjection);

        return $prefetched ?? [];
    }

    /**
     * @param array<string, mixed> $listingProjection
     * @return list<array<string, mixed>>|null
     */
    private function prefetchedEntries(string $sourceType, array $listingProjection): ?array
    {
        $blockPath = (string) ($this->context['blockPath'] ?? '');
        $allPrefetched = $this->context['block_prefetch'] ?? null;
        if ($blockPath === '' || ! is_array($allPrefetched) || ! is_array($allPrefetched[$blockPath] ?? null)) {
            return null;
        }

        $payload = $allPrefetched[$blockPath];
        $entries = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $entries = array_values(array_filter($entries, 'is_array'));

        if ($sourceType === 'cms_collection') {
            return $this->normalizeCmsEntries($entries, $listingProjection);
        }

        $source = $sourceType === 'event_items' ? $this->eventSource() : $this->catalogSource();
        $normalized = array_map(
            fn (array $entry): array => $source->normalizeEntry($entry),
            $entries,
        );

        return array_map(function (array $entry) use ($listingProjection): array {
            $slots = is_array($listingProjection['slots'] ?? null) ? $listingProjection['slots'] : [];
            $title = $this->projectionValue($entry, trim((string) ($slots['title'] ?? '')));
            $summary = $this->projectionValue($entry, trim((string) ($slots['summary'] ?? '')));
            $image = $this->projectionMedia($entry, trim((string) ($slots['image'] ?? '')));
            $date = $this->projectionValue($entry, trim((string) ($slots['date'] ?? '')));
            if ($title !== '') {
                $entry['title'] = $title;
            }
            if ($summary !== '') {
                $entry['excerpt'] = $summary;
            }
            if ($image !== null) {
                $entry['featured_image'] = $image;
            }
            if ($date !== '') {
                $entry['display_date'] = $date;
            }

            return $entry;
        }, $normalized);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $listingProjection
     * @return list<array<string, mixed>>
     */
    private function normalizeCmsEntries(array $entries, array $listingProjection): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $featuredImage = $this->mediaReferenceFromPayload($entry, 'featured_image');
            $entry['featured_image'] = $featuredImage['url'] !== '' ? $featuredImage : null;
            $listingContent = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
            $listingContent['video'] = ListingVideoPresentation::normalize(
                is_array($listingContent['video'] ?? null) ? $listingContent['video'] : null,
            );
            $entry['listing_content'] = $listingContent;
            $imageSource = is_array($listingProjection['slots'] ?? null) ? trim((string) ($listingProjection['slots']['image'] ?? '')) : '';
            $projectedImage = $this->projectionMedia($entry, $imageSource);
            if ($projectedImage !== null) {
                $entry['featured_image'] = $projectedImage;
            }
            $dateSource = is_array($listingProjection['slots'] ?? null) ? trim((string) ($listingProjection['slots']['date'] ?? '')) : '';
            $entry['display_date'] = $this->projectionValue($entry, $dateSource) ?: ListingDateResolver::resolve($entry, ListingDateResolver::isValidSource($dateSource) ? $dateSource : 'auto');
            $titleSource = is_array($listingProjection['slots'] ?? null) ? trim((string) ($listingProjection['slots']['title'] ?? '')) : '';
            $summarySource = is_array($listingProjection['slots'] ?? null) ? trim((string) ($listingProjection['slots']['summary'] ?? '')) : '';
            if ($titleSource !== '' && $this->projectionValue($entry, $titleSource) !== '') {
                $entry['title'] = $this->projectionValue($entry, $titleSource);
            }
            if ($summarySource !== '' && $this->projectionValue($entry, $summarySource) !== '') {
                $entry['excerpt'] = $this->projectionValue($entry, $summarySource);
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function eventSource(): EventItemsSource
    {
        return new EventItemsSource(
            fn (array $media): array => $this->normalizeMediaReference($media),
        );
    }

    private function catalogSource(): CatalogItemsSource
    {
        return new CatalogItemsSource(
            fn (array $media): array => $this->normalizeMediaReference($media),
        );
    }

    /** @return array<string, mixed> */
    private function listingProjection(): array
    {
        $projection = $this->config()['listing_projection'] ?? [];
        if (is_string($projection)) {
            $projection = json_decode($projection, true);
        }

        return is_array($projection) ? $projection : [];
    }

    /** @param array<string, mixed> $entry */
    private function projectionValue(array $entry, string $source): string
    {
        if (str_starts_with($source, 'entry.')) {
            $value = $entry[substr($source, 6)] ?? null;
            return is_scalar($value) ? trim((string) $value) : '';
        }
        $fields = is_array($entry['listing_content']['fields'] ?? null) ? $entry['listing_content']['fields'] : [];
        $value = $fields[$source] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>|null
     */
    private function projectionMedia(array $entry, string $source): ?array
    {
        if ($source === '') {
            return null;
        }
        $value = str_starts_with($source, 'entry.')
            ? ($entry[substr($source, 6)] ?? null)
            : (($entry['listing_content']['fields'] ?? [])[$source] ?? null);

        if (! is_array($value) || trim((string) ($value['url'] ?? '')) === '') {
            return null;
        }

        return $this->normalizeMediaReference($value);
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
