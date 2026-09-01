<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing\Sources;

use App\Support\Slug;
use App\ViewModels\Blocks\Listing\ListingPresentationSourceInterface;
use Closure;

/** Normalizes event rows already materialized by the BFF envelope. */
final class EventItemsSource implements ListingPresentationSourceInterface
{
    public function __construct(private Closure $mediaNormalizer)
    {
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
        $entry['slug'] = trim((string) ($localized['slug'] ?? $entry['slug'] ?? ''));
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
        $occurrence = $this->selectOccurrence($entry['occurrences'] ?? null);
        $entry['next_occurrence'] = $occurrence;
        $entry['start_time'] = (string) ($occurrence['start_time'] ?? $entry['next_occurrence_at'] ?? '');
        $entry['end_time'] = (string) ($occurrence['end_time'] ?? '');
        $entry['published_at'] = $entry['start_time'];

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
        $entry['tags'] = is_array($entry['tags'] ?? null)
            ? array_values(array_filter($entry['tags'], 'is_array'))
            : [];

        return $entry;
    }

    public function defaults(): array
    {
        return [
            'order_by' => 'start_time',
            'order_direction' => 'asc',
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

    private function eventTypeLabel(string $eventType): string
    {
        $translationKey = 'Site.event_type_' . $eventType;
        $translated = lang($translationKey);

        return $translated !== $translationKey
            ? $translated
            : ucwords(str_replace(['-', '_'], ' ', $eventType));
    }

    /** @return array<string, mixed> */
    private function selectOccurrence(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $last = [];
        $now = time();
        foreach ($value as $occurrence) {
            if (! is_array($occurrence)) {
                continue;
            }
            $last = $occurrence;
            $start = strtotime((string) ($occurrence['start_time'] ?? ''));
            if ($start !== false && $start >= $now) {
                return $occurrence;
            }
        }

        return $last;
    }
}
