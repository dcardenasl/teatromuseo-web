<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\Services\SiteEventService;
use App\Support\Slug;
use App\ViewModels\Blocks\Listing\ListingQuery;
use App\ViewModels\Blocks\Listing\ListingResult;
use App\ViewModels\Blocks\Listing\ListingSourceInterface;
use Closure;

class EventItemsSource implements ListingSourceInterface
{
    public function __construct(
        private SiteEventService $eventService,
        private Closure $urlBuilder,
        private Closure $mediaNormalizer
    ) {
    }

    public function fetch(ListingQuery $query, string $lang): ListingResult
    {
        $apiQuery = [
            'page' => $query->page,
            'per_page' => $query->perPage,
            'sort' => $query->orderBy ?: 'start_time',
            'filter' => ['status' => 'published'],
        ];

        if ($query->query !== '') {
            $apiQuery['search'] = $query->query;
        }

        if ($query->tag !== '') {
            $apiQuery['filter']['event_type'] = $query->tag;
        }

        try {
            $result = $this->eventService->listEvents($lang, $apiQuery);
            return new ListingResult($result['data'] ?? [], $result['meta']['pagination'] ?? []);
        } catch (\Throwable) {
            return new ListingResult();
        }
    }

    public function facets(ListingQuery $query, string $lang): array
    {
        $tags = [];

        foreach (['function', 'festival', 'course', 'workshop', 'other'] as $eventType) {
            $tagQuery = clone $query;
            $tagQuery->tag = $eventType;
            $tagQuery->category = '';
            $tagQuery->page = 1;

            $tags[] = [
                'slug' => $eventType,
                'name' => $this->eventTypeLabel($eventType),
                'url' => ($this->urlBuilder)($tagQuery),
            ];
        }

        return ['categories' => [], 'tags' => $tags];
    }

    public function normalizeEntry(array $entry): array
    {
        $localized = is_array($entry['localized'] ?? null) ? $entry['localized'] : [];
        $eventType = (string) ($entry['event_type'] ?? 'other');
        $title = trim((string) ($localized['title'] ?? $entry['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($entry['title'] ?? '');
        }

        $entry['title'] = $title;
        $entry['slug'] = trim((string) ($entry['slug'] ?? ''));
        if ($entry['slug'] === '') {
            $entry['slug'] = Slug::slugify($title);
        }
        if ($entry['slug'] === '') {
            $entry['slug'] = trim((string) ($entry['uuid'] ?? ''));
        }
        if ($entry['slug'] === '') {
            $entry['slug'] = (string) ($entry['id'] ?? '');
        }
        $entry['excerpt'] = (string) ($localized['description'] ?? $entry['description'] ?? '');
        $entry['published_at'] = (string) ($entry['start_time'] ?? '');

        $featuredImage = $entry['cover_image'] ?? $entry['featured_image'] ?? null;
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

        $entry['categories'] = [[
            'slug' => $eventType,
            'name' => $this->eventTypeLabel($eventType),
        ]];
        $entry['tags'] = [];

        return $entry;
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'start_time',
            'order_direction' => 'asc',
            // No 'source_path' here: the public URL segment for this source
            // type is locale-aware (PublicPaths::eventsSegment()) and is
            // resolved directly by CollectionListingViewModel, which has
            // the request locale that this locale-agnostic method lacks.
            'page_title' => lang('Site.event_listing_title'),
            'intro_text' => lang('Site.event_listing_intro'),
            'section_label' => lang('Site.event_listing_section'),
            'item_label' => lang('Site.event_item_label'),
            'featured_item_label' => lang('Site.event_featured_item_label'),
            'count_label' => lang('Site.event_listing_count'),
            'entry_cta_label' => lang('Site.view_event'),
            'show_categories' => false,
            'show_tags' => true,
            'show_date' => true,
        ];
    }

    public function previewResult(): ListingResult
    {
        return new ListingResult();
    }

    private function eventTypeLabel(string $eventType): string
    {
        return match ($eventType) {
            'function' => lang('Site.event_type_function'),
            'festival' => lang('Site.event_type_festival'),
            'course' => lang('Site.event_type_course'),
            'workshop' => lang('Site.event_type_workshop'),
            default => lang('Site.event_type_other'),
        };
    }
}
