<?php

declare(strict_types=1);

namespace App\Libraries;

class CacheInvalidator
{
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
     * @return array{invalidated: list<string>, deleted: int}
     */
    public function invalidate(array $scopes): array
    {
        $cache        = \Config\Services::cache();
        $invalidated  = [];
        $totalDeleted = 0;

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

        return ['invalidated' => $invalidated, 'deleted' => $totalDeleted];
    }

    /** @return list<string> */
    public static function validScopes(): array
    {
        return self::VALID_SCOPES;
    }
}
