<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

final class TeatroEscuelaViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $activityType = $this->activityType($this->dataString('activity_type', 'course'));
        $startDate = $this->dataString('start_date');
        $endDate = $this->dataString('end_date');
        $registrationUrl = $this->safeUrl($this->dataString('registration_url'));
        $videoEmbedUrl = VideoPlayerViewModel::embedUrl($this->dataString('video_url'), false, false);
        $status = $this->status($startDate, $endDate, $registrationUrl);

        return [
            'title' => $this->dataString('title', $this->dataString('name')),
            'activityTypeLabel' => $this->activityTypeLabel($activityType),
            'registerLabel' => str_replace('{activity_type}', $this->activityTypeLabel($activityType), lang('Site.theatre_school_register')),
            'summary' => $this->cleanHtml($this->dataString('summary', $this->dataString('description'))),
            'category' => $this->dataString('category'), 'modality' => $this->dataString('modality'),
            'startDate' => $startDate, 'endDate' => $endDate,
            'startDateLabel' => $this->dateLabel($startDate), 'endDateLabel' => $this->dateLabel($endDate),
            'schedule' => $this->dataString('schedule'), 'days' => $this->dataString('days'),
            'duration' => $this->dataString('duration'), 'venue' => $this->dataString('venue'),
            'capacity' => $this->dataString('capacity'), 'price' => $this->dataString('price'),
            'enrollmentFee' => $this->dataString('enrollment_fee'),
            'requirements' => $this->cleanHtml($this->dataString('requirements')),
            'objectives' => $this->cleanHtml($this->dataString('objectives')),
            'history' => $this->cleanHtml($this->dataString('history')),
            'instructors' => array_values(array_filter($this->dataArray('instructors'), 'is_array')),
            'registrationUrl' => $registrationUrl,
            'contactEmail' => $this->safeEmail($this->dataString('contact_email')),
            'videoEmbedUrl' => $videoEmbedUrl, 'status' => $status,
            'statusLabel' => $this->statusLabel($status),
        ];
    }

    private function status(string $startDate, string $endDate, string $registrationUrl): string
    {
        $today = date('Y-m-d');
        $end = $this->dateValue($endDate);
        $start = $this->dateValue($startDate);
        if ($end !== '' && $end < $today) {
            return 'finished';
        }
        if ($start !== '' && $start > $today) {
            return 'upcoming';
        }
        return $registrationUrl !== '' ? 'open' : '';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => str_replace('{activity_type}', $this->activityTypeLabel($this->activityType($this->dataString('activity_type', 'course'))), lang('Site.theatre_school_status_open')),
            'upcoming' => str_replace('{activity_type}', $this->activityTypeLabel($this->activityType($this->dataString('activity_type', 'course'))), lang('Site.theatre_school_status_upcoming')),
            'finished' => lang('Site.theatre_school_status_finished'), default => '',
        };
    }

    private function activityType(string $value): string
    {
        return in_array($value, ['course', 'workshop', 'residency', 'program', 'seminar', 'other'], true) ? $value : 'course';
    }

    private function activityTypeLabel(string $type): string
    {
        return lang('Site.theatre_school_activity_' . $type);
    }

    private function dateLabel(string $value): string
    {
        return $this->dateValue($value) !== '' ? format_localized_date($value, $this->lang) : '';
    }

    private function dateValue(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? '' : date('Y-m-d', $timestamp);
    }

    private function cleanHtml(string $value): string
    {
        return $value !== '' ? \App\Libraries\HtmlSanitizer::clean($value) : '';
    }

    private function safeUrl(string $url): string
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    }

    private function safeEmail(string $email): string
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }
}
