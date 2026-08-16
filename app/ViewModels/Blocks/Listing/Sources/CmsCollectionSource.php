<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\ViewModels\Blocks\Listing\ListingPresentationSourceInterface;
use Closure;

/** Normalizes CMS listing rows already materialized by the BFF envelope. */
final class CmsCollectionSource implements ListingPresentationSourceInterface
{
    public function __construct(private Closure $mediaNormalizer)
    {
    }

    public function normalizeEntry(array $entry): array
    {
        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $entry['title'] = (string) ($localized['title'] ?? $entry['title'] ?? '');
        $entry['slug'] = (string) ($localized['slug'] ?? $entry['slug'] ?? '');
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
        $entry['featured_image'] = is_array($featuredImage) || is_string($featuredImage)
            ? ($this->mediaNormalizer)($featuredImage)
            : null;

        return $entry;
    }

    /**
     * Reconstructs the canonical media reference used by older CMS responses.
     * A file ID without a URL remains unresolved instead of guessing a private
     * file route in the public application.
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
            'file_id' => is_numeric($fileId) && (int) $fileId > 0 ? (int) $fileId : null,
            'url' => is_scalar($url) ? trim((string) $url) : '',
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

        return is_scalar($value) ? trim((string) $value) : '';
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
}
