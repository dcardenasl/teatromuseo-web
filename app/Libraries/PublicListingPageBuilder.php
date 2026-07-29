<?php

declare(strict_types=1);

namespace App\Libraries;

use InvalidArgumentException;

final class PublicListingPageBuilder
{
    /**
     * Build the museum catalog listing page.
     *
     * @return array{page: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function museum(string $lang): array
    {
        return $this->build($lang, 'catalog_items');
    }

    /**
     * Build the public event listing page.
     *
     * @return array{page: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public function event(string $lang): array
    {
        return $this->build($lang, 'event_items');
    }

    /**
     * @return array{page: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private function build(string $lang, string $sourceType): array
    {
        $spec = $this->spec($sourceType);
        $localizedUrls = [];

        foreach (config('App')->supportedLocales as $locale) {
            $localizedUrls[$locale] = lang_url($spec['source_path'], $locale);
        }

        $page = [
            'title' => $spec['page_title'],
            'excerpt' => $spec['intro_text'],
            'showPageHeading' => false,
            'pageTitle' => $spec['page_title'],
            'metaDescription' => $spec['intro_text'],
            'canonicalUrl' => lang_url($spec['source_path'], $lang),
            'ogImage' => '',
            'metaRobots' => 'index, follow',
            'schemaData' => null,
            'localized_urls' => $localizedUrls,
        ];

        return [
            'page' => $page,
            'blocks' => [
                [
                    'block_key' => 'collection_listing',
                    'block_config' => [
                        'source_type' => $sourceType,
                        'source_path' => $spec['source_path'],
                        'per_page' => 12,
                        'order_by' => $spec['order_by'],
                        'order_direction' => $spec['order_direction'],
                        'layout_variant' => 'cards',
                        'css_class' => $spec['css_class'],
                        'show_search' => true,
                        'show_categories' => $spec['show_categories'],
                        'show_tags' => $spec['show_tags'],
                        'show_excerpt' => true,
                        'show_date' => $spec['show_date'],
                        'show_button' => true,
                        'show_item_categories' => true,
                        'show_extra_richtext' => false,
                        'show_extra_link' => false,
                        'show_extra_image' => false,
                        'section_label' => $spec['section_label'],
                        'intro_title' => $spec['page_title'],
                        'intro_text' => $spec['intro_text'],
                        'item_label' => $spec['item_label'],
                        'featured_item_label' => $spec['featured_item_label'],
                        'count_label' => $spec['count_label'],
                        'entry_cta_label' => $spec['entry_cta_label'],
                        'fallback_image_url' => $spec['fallback_image_url'],
                    ],
                    'block_data' => [],
                    'children' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(string $sourceType): array
    {
        return match ($sourceType) {
            'catalog_items' => [
                'source_path' => \App\Support\PublicPaths::CATALOG,
                'page_title' => lang('Site.museum_collection_title'),
                'intro_text' => lang('Site.museum_collection_intro'),
                'section_label' => lang('Site.museum_collection_section'),
                'item_label' => lang('Site.museum_item_label'),
                'featured_item_label' => lang('Site.museum_featured_item_label'),
                'count_label' => lang('Site.museum_listing_count'),
                'entry_cta_label' => lang('Site.view_sheet'),
                'order_by' => 'name',
                'order_direction' => 'asc',
                'show_categories' => true,
                'show_tags' => false,
                'show_date' => false,
                'css_class' => 'public-listing public-listing--museum',
                'fallback_image_url' => 'https://images.unsplash.com/photo-1544928147-79a2dbc1f389?auto=format&fit=crop&w=600&q=80',
            ],
            'event_items' => [
                'source_path' => \App\Support\PublicPaths::EVENTS,
                'page_title' => lang('Site.event_listing_title'),
                'intro_text' => lang('Site.event_listing_intro'),
                'section_label' => lang('Site.event_listing_section'),
                'item_label' => lang('Site.event_item_label'),
                'featured_item_label' => lang('Site.event_featured_item_label'),
                'count_label' => lang('Site.event_listing_count'),
                'entry_cta_label' => lang('Site.view_event'),
                'order_by' => 'start_time',
                'order_direction' => 'asc',
                'show_categories' => false,
                'show_tags' => true,
                'show_date' => true,
                'css_class' => 'public-listing public-listing--event',
                'fallback_image_url' => 'https://images.unsplash.com/photo-1507676184212-d0330a15183e?auto=format&fit=crop&w=600&q=80',
            ],
            default => throw new InvalidArgumentException('Unsupported public listing source: ' . $sourceType),
        };
    }
}
