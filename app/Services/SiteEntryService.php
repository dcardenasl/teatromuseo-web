<?php

declare(strict_types=1);

namespace App\Services;

class SiteEntryService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300; // 5 minutes for single entry
    private const CACHE_TTL_LIST = 180;   // 3 minutes for list (more dynamic)

    /**
     * List entries in a collection with optional pagination and filtering.
     *
     * @param array<string, mixed> $query Query parameters: page, limit, category, tag, etc.
     *
     * @return array<string, mixed> {data: entries[], meta: {pagination: ...}}
     */
    public function list(string $lang, string $collectionKey, array $query = []): array
    {
        $response = $this->apiClient->get(
            "public-read/{$lang}/entries/{$collectionKey}",
            $query,
            self::CACHE_TTL_LIST,
            'entries'
        );

        if (! $response['ok']) {
            return ['data' => [], 'meta' => ['pagination' => []]];
        }

        return [
            'data' => is_array($response['data']) ? $response['data'] : [],
            'meta' => [
                'pagination' => $this->normalizePagination(
                    is_array($response['meta'] ?? null) ? $response['meta'] : [],
                    isset($query['page']) ? (int) $query['page'] : 1,
                    isset($query['per_page']) ? (int) $query['per_page'] : 20
                ),
            ],
        ];
    }

    /**
     * Get a single entry by slug (with optional preview support).
     *
     * preview_expires/preview_sig are forwarded opaquely — this app never
     * validates them, only Domain does (PreviewToken::verify).
     *
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $lang, string $collectionKey, string $slug, bool $preview = false, ?string $previewExpires = null, ?string $previewSig = null): ?array
    {
        $query = [];
        if ($preview) {
            $query['preview'] = '1';
            if ($previewExpires !== null) {
                $query['preview_expires'] = $previewExpires;
            }
            if ($previewSig !== null) {
                $query['preview_sig'] = $previewSig;
            }
        }
        $ttl = $preview ? 0 : self::CACHE_TTL_DETAIL;

        return $this->fetchData(
            "public-read/{$lang}/entries/{$collectionKey}/{$slug}",
            $query,
            $ttl,
            'entries'
        );
    }

    /**
     * Entries related to the given one: same collection, preferring a shared
     * category, always excluding the entry itself.
     *
     * @param array<string, mixed> $entry Current entry (needs 'slug' and optionally 'categories')
     *
     * @return list<array<string, mixed>>
     */
    public function related(string $lang, string $collectionKey, array $entry, int $limit = 3): array
    {
        $currentSlug = (string) ($entry['slug'] ?? '');
        $categories = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        $categorySlug = is_array($categories[0] ?? null) ? (string) ($categories[0]['slug'] ?? '') : '';

        $baseQuery = [
            'per_page'        => $limit + 1,
            'order_by'        => 'published_at',
            'order_direction' => 'desc',
        ];

        $entries = [];
        if ($categorySlug !== '') {
            $entries = $this->filteredList($lang, $collectionKey, $baseQuery + ['category' => $categorySlug], $currentSlug);
        }

        if (count($entries) < $limit) {
            $entries = $this->filteredList($lang, $collectionKey, $baseQuery, $currentSlug);
        }

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return list<array<string, mixed>>
     */
    private function filteredList(string $lang, string $collectionKey, array $query, string $excludeSlug): array
    {
        $result = $this->list($lang, $collectionKey, $query);
        $entries = is_array($result['data'] ?? null) ? $result['data'] : [];

        return array_values(array_filter(
            $entries,
            static fn ($e) => is_array($e) && (string) ($e['slug'] ?? '') !== $excludeSlug
        ));
    }
}
