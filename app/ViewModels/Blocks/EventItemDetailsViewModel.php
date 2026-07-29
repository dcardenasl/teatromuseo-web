<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class EventItemDetailsViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Detalles de Evento'),
            ];
        }

        $startTime = (string) ($event['start_time'] ?? '');
        $endTime = (string) ($event['end_time'] ?? '');
        $startTimeLabel = $this->formatDisplayDateTime($startTime);
        $endTimeLabel = $this->formatDisplayDateTime($endTime);
        $startTimeIso = $this->formatIsoDateTime($startTime);
        $endTimeIso = $this->formatIsoDateTime($endTime);
        $venue = (string) ($event['venue'] ?? '');
        $capacity = $this->formatInteger($event['capacity'] ?? null);
        $availableSpots = $this->formatInteger($event['available_spots'] ?? null);
        $status = $this->formatStatusLabel((string) ($event['status'] ?? ''));

        return [
            'hasEvent' => true,
            'startTime' => $startTimeLabel,
            'endTime' => $endTimeLabel,
            'startTimeIso' => $startTimeIso,
            'endTimeIso' => $endTimeIso,
            'venue' => $venue,
            'capacity' => $capacity,
            'availableSpots' => $availableSpots,
            'status' => $status,
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

    /**
     * @param mixed $value
     */
    private function formatInteger(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return '';
    }

    private function formatStatusLabel(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return '';
        }

        if ($status === 'published') {
            return (string) (lang('Site.published_label') ?: 'Published');
        }

        return ucwords(str_replace('_', ' ', $status));
    }
}
