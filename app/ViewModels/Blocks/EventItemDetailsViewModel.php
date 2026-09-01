<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\ViewModels\Blocks\Concerns\FormatsLocalizedDateTime;

class EventItemDetailsViewModel extends AbstractBlockViewModel
{
    use FormatsLocalizedDateTime;

    public function vars(): array
    {
        $event = $this->context['event_item'] ?? null;
        if (!is_array($event)) {
            return [
                'hasEvent' => false,
                'fallbackTitle' => $this->dataString('fallback_title', lang('Site.event_details_preview_title')),
            ];
        }

        $occurrences = $this->formatOccurrences($event['occurrences'] ?? null);
        $occurrence = $occurrences[0] ?? [];
        $startTime = (string) ($occurrence['start_time'] ?? '');
        $endTime = (string) ($occurrence['end_time'] ?? '');
        $startTimeLabel = (string) ($occurrence['start_label'] ?? '');
        $endTimeLabel = (string) ($occurrence['end_label'] ?? '');
        $startTimeIso = (string) ($occurrence['start_iso'] ?? '');
        $endTimeIso = (string) ($occurrence['end_iso'] ?? '');
        $venue = (string) ($occurrence['venue_name'] ?? '');
        $capacity = $this->formatInteger($occurrence['capacity'] ?? null);
        $availableSpots = $this->formatInteger($occurrence['available_spots'] ?? null);
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
            'occurrences' => $occurrences,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function formatOccurrences(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $occurrence) {
            if (! is_array($occurrence)) {
                continue;
            }

            $timezone = (string) ($occurrence['timezone'] ?? 'America/Santiago');
            $start = (string) ($occurrence['start_time'] ?? '');
            $end = (string) ($occurrence['end_time'] ?? '');
            $rows[] = $occurrence + [
                'start_label' => $this->formatDisplayDateTime($start, $this->lang, $timezone),
                'end_label' => $this->formatDisplayDateTime($end, $this->lang, $timezone),
                'start_iso' => $this->formatIsoDateTime($start, $timezone),
                'end_iso' => $this->formatIsoDateTime($end, $timezone),
            ];
        }

        $now = time();
        usort($rows, static function (array $left, array $right) use ($now): int {
            $leftStart = strtotime((string) ($left['start_time'] ?? '')) ?: 0;
            $rightStart = strtotime((string) ($right['start_time'] ?? '')) ?: 0;
            $leftFuture = $leftStart >= $now;
            $rightFuture = $rightStart >= $now;
            if ($leftFuture !== $rightFuture) {
                return $leftFuture ? -1 : 1;
            }

            return $leftFuture ? $leftStart <=> $rightStart : $rightStart <=> $leftStart;
        });

        return $rows;
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

        $statusKey = match ($status) {
            'published' => 'published_label',
            'draft' => 'event_status_draft',
            'cancelled' => 'event_status_cancelled',
            'sold_out' => 'event_status_sold_out',
            default => null,
        };

        return $statusKey !== null ? (string) lang('Site.' . $statusKey) : $status;
    }
}
