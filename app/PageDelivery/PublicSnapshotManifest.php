<?php

declare(strict_types=1);

namespace App\PageDelivery;

/**
 * Explicit allow-list for warm-up. It intentionally does not crawl URLs or
 * derive routes from user content.
 */
final class PublicSnapshotManifest
{
    /** @return list<PageDeliveryRequest> */
    public function requests(?string $locale = null, ?string $route = null): array
    {
        $config = config('App');
        if ($locale !== null && ! in_array($locale, $config->supportedLocales, true)) {
            return [];
        }
        if ($route !== null && ! in_array($route, $config->pageSnapshotManifestRoutes, true)) {
            return [];
        }

        $locales = $locale !== null ? [$locale] : $config->supportedLocales;
        $routes = $route !== null ? [$route] : $config->pageSnapshotManifestRoutes;

        $requests = [];
        foreach ($locales as $language) {
            foreach ($routes as $publicRoute) {
                $requests[] = new PageDeliveryRequest((string) $language, (string) $publicRoute);
            }
        }

        return $requests;
    }
}
