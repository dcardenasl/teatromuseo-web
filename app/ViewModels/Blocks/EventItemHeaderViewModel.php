<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class EventItemHeaderViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Cabecera de Evento'),
            ];
        }

        $title = $event['title'] ?? $event['name'] ?? 'Evento sin título';
        $summary = $event['localized']['description'] ?? $event['description'] ?? '';

        $image = $event['cover_image'] ?? $event['featured_image'] ?? null;
        $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
        if ($imageUrl === '') {
            $imageUrl = $this->configString('fallback_image_url', '');
        }

        $startTime = (string) ($event['start_time'] ?? '');
        $endTime = (string) ($event['end_time'] ?? '');
        $startTimeLabel = $this->formatDisplayDateTime($startTime);
        $endTimeLabel = $this->formatDisplayDateTime($endTime);
        $startTimeIso = $this->formatIsoDateTime($startTime);
        $endTimeIso = $this->formatIsoDateTime($endTime);
        $venue = (string) ($event['venue'] ?? '');
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
            'homeLabel' => lang('Site.breadcrumb_home') ?: 'Inicio',
            'breadcrumbUrl' => lang_url(\App\Support\PublicPaths::EVENTS),
            'breadcrumbLabel' => 'Cartelera',
        ];
    }

    private function formatIsoDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date(DATE_ATOM, $timestamp);
    }

    private function formatDisplayDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('d M Y · H:i', $timestamp);
    }
}
