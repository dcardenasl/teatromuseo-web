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
    protected function formatIsoDateTime(string $value, string $timezone = 'America/Santiago'): string
    {
        $date = $this->parseDateTime($value, $timezone);
        if ($date === null) {
            return '';
        }

        return $date->format(DATE_ATOM);
    }

    protected function formatDisplayDateTime(string $value, string $lang, string $timezone = 'America/Santiago'): string
    {
        $date = $this->parseDateTime($value, $timezone);
        if ($date === null) {
            return '';
        }

        $timestamp = $date->getTimestamp();

        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $this->intlLocale($lang),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
            );
            $formatter->setTimeZone($date->getTimezone());
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return $date->format('d-m-Y H:i');
    }

    private function parseDateTime(string $value, string $timezone): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone($timezone));
        } catch (\Exception) {
            return null;
        }
    }

    private function intlLocale(string $lang): string
    {
        return localized_date_intl_locale($lang);
    }
}
