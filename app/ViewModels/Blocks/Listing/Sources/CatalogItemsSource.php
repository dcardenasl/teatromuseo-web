<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\Support\Slug;
use App\ViewModels\Blocks\Listing\ListingPresentationSourceInterface;
use Closure;

/** Normalizes catalog rows already materialized by the BFF envelope. */
final class CatalogItemsSource implements ListingPresentationSourceInterface
{
    public function __construct(private Closure $mediaNormalizer)
    {
    }

    public function normalizeEntry(array $entry): array
    {
        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $title = (string) ($localized['name'] ?? $entry['name'] ?? $entry['title'] ?? '');
        $entry['title'] = $title;
        $entry['slug'] = trim((string) ($localized['slug'] ?? $entry['slug'] ?? $entry['inventory_code'] ?? ''));
        if ($entry['slug'] === '') {
            $entry['slug'] = Slug::slugify($title);
        }
        if ($entry['slug'] === '') {
            $entry['slug'] = (string) ($entry['id'] ?? '');
        }
        $entry['excerpt'] = (string) ($localized['summary'] ?? $entry['summary'] ?? '');
        $entry['published_at'] = (string) ($entry['created_at'] ?? $entry['updated_at'] ?? '');

        $featuredImage = $entry['cover_image'] ?? $entry['featured_image'] ?? $entry['main_image'] ?? null;
        if (is_array($featuredImage)) {
            $entry['featured_image'] = ($this->mediaNormalizer)($featuredImage);
        } elseif (is_string($featuredImage) && trim($featuredImage) !== '') {
            $entry['featured_image'] = [
                'source_kind' => 'external_url',
                'file_id' => null,
                'url' => trim($featuredImage),
            ];
        } else {
            $entry['featured_image'] = null;
        }

        $entry['categories'] = is_array($entry['categories'] ?? null)
            ? array_values(array_filter($entry['categories'], 'is_array'))
            : [];
        $entry['tags'] = is_array($entry['tags'] ?? null)
            ? array_values(array_filter($entry['tags'], 'is_array'))
            : [];

        return $entry;
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'name',
            'order_direction' => 'asc',
            'page_title' => lang('Site.museum_collection_title'),
            'intro_text' => lang('Site.museum_collection_intro'),
            'section_label' => lang('Site.museum_collection_section'),
            'item_label' => lang('Site.museum_item_label'),
            'featured_item_label' => lang('Site.museum_featured_item_label'),
            'count_label' => lang('Site.museum_listing_count'),
            'entry_cta_label' => lang('Site.view_sheet'),
            'show_categories' => true,
            'show_tags' => false,
            'show_date' => false,
        ];
    }
}
