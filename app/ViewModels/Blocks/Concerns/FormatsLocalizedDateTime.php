<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Concerns;

/**
 * Shared date formatting for block view models that display event/session
 * timestamps. Display strings are locale-aware (month names follow the
 * current page language); ISO strings stay locale-independent for the
 * `datetime` attribute.
 */
trait FormatsLocalizedDateTime
{
    protected function formatIsoDateTime(string $value): string
    {
        $timestamp = $this->parseTimestamp($value);
        if ($timestamp === null) {
            return '';
        }

        return date(DATE_ATOM, $timestamp);
    }

    protected function formatDisplayDateTime(string $value, string $lang): string
    {
        $timestamp = $this->parseTimestamp($value);
        if ($timestamp === null) {
            return '';
        }

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $this->intlLocale($lang),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
            );
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('d-m-Y H:i', $timestamp);
    }

    private function parseTimestamp(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    private function intlLocale(string $lang): string
    {
        return match ($lang) {
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'pt' => 'pt_PT',
            default => 'es_ES',
        };
    }
}
