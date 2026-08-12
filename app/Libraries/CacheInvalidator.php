<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Support\PublicPaths;

class CacheInvalidator
{
    private const STATUS_CACHE_KEY = 'public_site_cache_invalidation_status_v1';

    /** @var list<string> */
    private const VALID_SOURCES = [
        'cms_automatic',
        'admin_content_write',
        'admin_manual',
        'remote',
    ];

    /** @var list<string> */
    private const VALID_SCOPES = [
        'settings',
        'menus',
        'pages',
        'collections',
        'entries',
        'taxonomies',
        'events',
        'event_types',
        'categories',
        'techniques',
        'collection_items',
        'redirects',
        'forms',
    ];

    /**
     * Delete all cached web API responses for the given content scopes.
     *
     * Uses CI4's deleteMatching() (glob-based) so only the targeted prefix is cleared,
     * never the entire cache store.
     *
     * @param list<string> $scopes
     * @param list<string> $locales
     * @param list<string> $routes
     * @return array{invalidated: list<string>, deleted: int, snapshots_invalidated: int, response_cache_deleted: int}
     */
    public function invalidate(array $scopes, string $source = 'remote', array $locales = [], array $routes = []): array
    {
        $cache        = \Config\Services::cache();
        $invalidated  = [];
        $totalDeleted = 0;
        $snapshotsInvalidated = 0;

        $scopes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $scope): string => is_scalar($scope) ? strtolower(trim((string) $scope)) : '',
            $scopes,
        ))));

        foreach ($scopes as $scope) {
            if (! in_array($scope, self::VALID_SCOPES, true)) {
                log_message('warning', '[CacheInvalidator] Unknown scope requested: ' . $scope);
                continue;
            }

            $deleted      = $cache->deleteMatching('web_api_*_' . $scope . '_*');
            $totalDeleted += $deleted;
            $invalidated[] = $scope;

            log_message('info', "[CacheInvalidator] Scope '{$scope}': {$deleted} cache entries deleted.");

            if (in_array($scope, ['pages', 'collections', 'entries'], true)) {
                $cache->deleteMatching('sitemap_*');
            }
        }

        if ($invalidated !== []) {
            $responseCacheDeleted = \Config\Services::htmlResponseCacheRegistry()->invalidate(
                $invalidated,
                $locales,
                $routes,
            );
            if ($this->shouldInvalidateHomepageResponseCache($invalidated, $routes)) {
                $responseCacheDeleted += $this->invalidateHomepageResponseCache($locales);
            }

            $snapshotStore = \Config\Services::pageSnapshotStore();
            $snapshotsInvalidated = $snapshotStore->invalidateScopes($invalidated, $locales, $routes);
            $this->recordStatus($invalidated, $totalDeleted, $source);
        } else {
            $responseCacheDeleted = 0;
        }

        return [
            'invalidated' => $invalidated,
            'deleted' => $totalDeleted,
            'snapshots_invalidated' => $snapshotsInvalidated,
            'response_cache_deleted' => $responseCacheDeleted,
        ];
    }

    /**
     * Decide whether the opaque full-page cache needs a homepage purge.
     *
     * API/snapshot scopes are not a complete dependency graph for rendered
     * HTML. The homepage currently embeds Events, CMS collections, layout
     * data and other public-read sources, while the admin and domain outbox
     * producers intentionally omit route metadata. With no route filter, a
     * known public-scope invalidation must therefore conservatively invalidate
     * the localized homepage aliases as well. A non-home route does not
     * suppress this purge because the homepage is a composite consumer of
     * multiple scopes; route metadata remains a snapshot selector only.
     *
     * @param list<string> $scopes
     * @param list<string> $routes
     */
    private function shouldInvalidateHomepageResponseCache(array $scopes, array $routes): bool
    {
        $routes = array_values(array_filter(
            array_map(static fn (mixed $route): string => strtolower(trim((string) $route)), $routes),
            static fn (string $route): bool => $route !== '',
        ));

        if (in_array('home', $routes, true)) {
            return true;
        }

        return $scopes !== [];
    }

    /**
     * Delete full HTML cache entries for the localized homepage aliases.
     *
     * ResponseCache uses an opaque md5 key rather than a scope prefix. The
     * homepage is the one named route whose canonical and legacy paths are
     * known here, so invalidate both forms without flushing unrelated caches.
     * The relative and absolute variants cover CodeIgniter's URI
     * normalization in local and hosted environments.
     *
     * @param list<string> $locales
     */
    private function invalidateHomepageResponseCache(array $locales): int
    {
        $configuredLocales = config('App')->supportedLocales;
        $locales = $locales !== [] ? $locales : $configuredLocales;
        $cache = \Config\Services::cache();
        $deleted = 0;
        $paths = [];

        foreach ($locales as $locale) {
            $locale = strtolower(trim((string) $locale));
            if ($locale === '') {
                continue;
            }

            $paths[] = '/' . $locale;
            $paths[] = '/' . $locale . '/' . trim(PublicPaths::homepageSegment($locale), '/');
            $paths[] = '/' . $locale . '/public/' . $locale;
        }

        foreach (array_values(array_unique($paths)) as $path) {
            foreach ([$path, site_url($path)] as $uri) {
                if ($cache->delete(md5('GET:' . $uri))) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Return operational metadata for the public-site cache invalidation flow.
     *
     * The status item intentionally uses TTL 0 so it survives normal cache
     * expiry. A full cache clean removes it, in which case the UI reports that
     * no successful invalidation has been observed since the clean.
     *
     * @return array{
     *     configured: bool,
     *     handler: string,
     *     last_invalidation_at: string|null,
     *     last_invalidation_source: string|null,
     *     last_invalidation_scopes: list<string>,
     *     last_deleted: int,
     *     last_automatic_invalidation_at: string|null,
     *     last_manual_invalidation_at: string|null
     * }
     */
    public function status(): array
    {
        $stored = \Config\Services::cache()->get(self::STATUS_CACHE_KEY);
        $stored = is_array($stored) ? $stored : [];

        return [
            'configured' => trim((string) env('CACHE_INVALIDATE_KEY', '')) !== '',
            'handler' => (string) config('Cache')->handler,
            'snapshot_backend' => \Config\Services::pageSnapshotStore()->status(),
            'last_invalidation_at' => $this->nullableString($stored['last_invalidation_at'] ?? null),
            'last_invalidation_source' => $this->nullableString($stored['last_invalidation_source'] ?? null),
            'last_invalidation_scopes' => $this->stringList($stored['last_invalidation_scopes'] ?? []),
            'last_deleted' => max(0, (int) ($stored['last_deleted'] ?? 0)),
            'last_automatic_invalidation_at' => $this->nullableString($stored['last_automatic_invalidation_at'] ?? null),
            'last_manual_invalidation_at' => $this->nullableString($stored['last_manual_invalidation_at'] ?? null),
        ];
    }

    /**
     * @param list<string> $scopes
     */
    private function recordStatus(array $scopes, int $deleted, string $source): void
    {
        $now = gmdate('c');
        $source = trim($source);
        $source = in_array($source, self::VALID_SOURCES, true) ? $source : 'remote';
        $status = $this->status();
        $status['last_invalidation_at'] = $now;
        $status['last_invalidation_source'] = $source;
        $status['last_invalidation_scopes'] = array_values($scopes);
        $status['last_deleted'] = max(0, $deleted);

        if ($source === 'admin_manual') {
            $status['last_manual_invalidation_at'] = $now;
        } else {
            $status['last_automatic_invalidation_at'] = $now;
        }

        \Config\Services::cache()->save(self::STATUS_CACHE_KEY, $status, 0);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /** @return list<string> */
    public static function validScopes(): array
    {
        return self::VALID_SCOPES;
    }
}
