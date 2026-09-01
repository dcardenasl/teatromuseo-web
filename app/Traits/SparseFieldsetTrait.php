<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * SparseFieldsetTrait — Parse and apply ?fields= query parameter to filter response fields.
 *
 * Allows clients to request only specific fields from an API response, reducing
 * payload size and network overhead for listing endpoints.
 *
 * Usage in controllers:
 *   use SparseFieldsetTrait;
 *   $fields = $this->parseFieldsParam();
 *   if ($fields !== null) {
 *       $item = $this->sparseFilter($item, $fields);
 *   }
 */
trait SparseFieldsetTrait
{
    /**
     * Parse ?fields= query parameter from the request.
     *
     * Returns a list of allowed field names, or null if not specified.
     * Always includes 'id' and 'localized' for consistency across resources.
     *
     * Example:
     *   GET /api/v1/public/collection-items?fields=name,slug,cover_file_id
     *   Returns: ['id', 'name', 'slug', 'cover_file_id', 'localized']
     *
     * @return list<string>|null null if ?fields= is not present or empty
     */
    protected function parseFieldsParam(): ?array
    {
        $request = service('request');
        $fields = $request->getGet('fields');

        if (!is_string($fields) || trim($fields) === '') {
            return null;
        }

        // Parse comma-separated list
        $parsed = array_map('trim', explode(',', $fields));
        $parsed = array_filter($parsed, static fn ($f) => $f !== '');

        if ($parsed === []) {
            return null;
        }

        // Always include 'id' and 'localized' for consistency
        $parsed = array_unique(array_merge(['id'], $parsed));

        return array_values($parsed);
    }

    /**
     * Filter an item array to include only specified fields.
     *
     * @param array<string, mixed> $item
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    protected function sparseFilter(array $item, array $allowed): array
    {
        return array_intersect_key($item, array_flip($allowed));
    }

    /**
     * Apply sparse fieldset filtering to a list of items.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $allowed
     * @return list<array<string, mixed>>
     */
    protected function sparseFilterList(array $items, array $allowed): array
    {
        return array_map(
            fn (array $item): array => $this->sparseFilter($item, $allowed),
            $items
        );
    }
}
