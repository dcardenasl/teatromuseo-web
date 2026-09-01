<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks\Listing;

/** Resolves the public date slot without exposing CMS block storage details. */
final class ListingDateResolver
{
    private const SOURCES = ['published_at', 'created_at', 'listing.publication_date', 'listing.start_date', 'listing.end_date', 'listing.opening_date', 'listing.closing_date', 'listing.premiere_date', 'listing.performance_date', 'listing.recorded_at'];

    /** @param array<string, mixed> $entry */
    public static function resolve(array $entry, string $source = 'auto'): string
    {
        $listing = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
        $dateFields = is_array($listing['date_fields'] ?? null) ? $listing['date_fields'] : [];

        if ($source === 'auto') {
            return self::firstNonEmpty([$entry['display_date'] ?? null, $entry['published_at'] ?? null, $entry['created_at'] ?? null]);
        }
        if (in_array($source, ['published_at', 'created_at'], true)) {
            return self::scalar($entry[$source] ?? null);
        }
        if (str_starts_with($source, 'listing.')) {
            $field = substr($source, 8);
            return isset($dateFields[$field]) ? self::scalar($dateFields[$field]) : '';
        }
        return '';
    }

    public static function isValidSource(string $source): bool
    {
        return $source === 'auto' || in_array($source, self::SOURCES, true);
    }

    /** @param list<mixed> $values */
    private static function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = self::scalar($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
