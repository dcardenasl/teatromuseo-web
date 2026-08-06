<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\ViewModels\Blocks\Concerns\FormatsLocalizedDateTime;

class EventItemHeaderViewModel extends AbstractBlockViewModel
{
    use FormatsLocalizedDateTime;

    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', lang('Site.event_header_preview_title')),
            ];
        }

        $localized = is_array($event['localized'] ?? null) ? $event['localized'] : [];
        $title = $localized['title'] ?? $localized['name'] ?? $event['title'] ?? $event['name'] ?? lang('Site.event_untitled');
        $summary = $localized['description'] ?? $event['description'] ?? '';

        // No fallback to configString('fallback_image_url', '') here on purpose: that config
        // key holds an admin-authored placeholder meant only for the block-editor preview (the
        // !hasEvent branch above), not for a real event that simply has no cover of its own —
        // showing a generic stock photo as if it belonged to a specific event was misleading.
        // The view already hides the <figure> entirely when imageUrl is empty.
        $image = $event['cover_image'] ?? $event['featured_image'] ?? null;
        $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');

        $occurrence = $this->selectOccurrence($event['occurrences'] ?? null);
        $timezone = (string) ($occurrence['timezone'] ?? 'America/Santiago');
        $startTime = (string) ($occurrence['start_time'] ?? '');
        $endTime = (string) ($occurrence['end_time'] ?? '');
        $startTimeLabel = $this->formatDisplayDateTime($startTime, $this->lang, $timezone);
        $endTimeLabel = $this->formatDisplayDateTime($endTime, $this->lang, $timezone);
        $startTimeIso = $this->formatIsoDateTime($startTime, $timezone);
        $endTimeIso = $this->formatIsoDateTime($endTime, $timezone);
        $venue = (string) ($occurrence['venue_name'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');

        $eventTypeLabel = match ($eventType) {
            'function' => lang('Site.event_type_function'),
            'festival' => lang('Site.event_type_festival'),
            'course' => lang('Site.event_type_course'),
            'workshop' => lang('Site.event_type_workshop'),
            default => lang('Site.event_type_other'),
        };

        return [
            'hasEvent' => true,
            'title' => $title,
            'summary' => $summary,
            'imageUrl' => $imageUrl,
            'startTime' => $startTimeLabel,
            'endTime' => $endTimeLabel,
            'startTimeIso' => $startTimeIso,
            'endTimeIso' => $endTimeIso,
            'venue' => $venue,
            'eventTypeLabel' => $eventTypeLabel,
            'homeLabel' => lang('Site.breadcrumb_home'),
            'breadcrumbUrl' => lang_url(\App\Support\PublicPaths::eventsSegment($this->lang), $this->lang),
            'breadcrumbLabel' => lang('Site.event_listing_title'),
        ];
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
