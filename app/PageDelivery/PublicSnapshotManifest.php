<?php

declare(strict_types=1);

namespace App\PageDelivery;

use App\Support\PublicPaths;

/**
 * Explicit allow-list for warm-up. It intentionally does not crawl URLs or
 * derive routes from user content.
 */
final class PublicSnapshotManifest
{
    /**
     * Return the configured delivery identity for a concrete public route.
     *
     * The manifest may use stable route keys (`events`, `catalog`) so the same
     * explicit allow-list works for every locale. Arbitrary CMS paths remain
     * literal and must be listed explicitly by the deployment.
     *
     * @param array<string, mixed> $query
     */
    public function requestFor(
        string $locale,
        string $route,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSignature = null,
        array $query = [],
    ): ?PageDeliveryRequest {
        $config = config('App');
        $locale = strtolower(trim($locale));
        $route = trim($route, '/');
        if (! in_array($locale, $config->supportedLocales, true) || $route === '') {
            return null;
        }

        foreach ($config->pageSnapshotManifestRoutes as $configuredRoute) {
            $resolvedRoute = $this->resolveRoute((string) $configuredRoute, $locale);
            if ($resolvedRoute !== $route) {
                continue;
            }

            return new PageDeliveryRequest(
                locale: $locale,
                route: $resolvedRoute,
                preview: $preview,
                previewExpires: $previewExpires,
                previewSignature: $previewSignature,
                query: $query,
            );
        }

        return null;
    }

    /** @return list<PageDeliveryRequest> */
    public function requests(?string $locale = null, ?string $route = null): array
    {
        $config = config('App');
        if ($locale !== null && ! in_array($locale, $config->supportedLocales, true)) {
            return [];
        }
        if ($route !== null && ! $this->manifestContains($route, $locale)) {
            return [];
        }

        $locales = $locale !== null ? [$locale] : $config->supportedLocales;
        $routes = $route !== null
            ? [$this->configuredRoute($route, $locale)]
            : $config->pageSnapshotManifestRoutes;

        $requests = [];
        $seen = [];
        foreach ($locales as $language) {
            foreach ($routes as $publicRoute) {
                $request = $this->requestFor((string) $language, $this->resolveRoute((string) $publicRoute, (string) $language));
                if ($request === null || isset($seen[$request->cacheKey()])) {
                    continue;
                }

                $seen[$request->cacheKey()] = true;
                $requests[] = $request;
            }
        }

        return $requests;
    }

    private function manifestContains(string $route, ?string $locale): bool
    {
        $route = trim($route, '/');
        if ($route === '') {
            return false;
        }

        $locales = $locale !== null ? [$locale] : config('App')->supportedLocales;
        foreach (config('App')->pageSnapshotManifestRoutes as $configuredRoute) {
            if (trim((string) $configuredRoute, '/') === $route) {
                return true;
            }

            foreach ($locales as $language) {
                if ($this->resolveRoute((string) $configuredRoute, (string) $language) === $route) {
                    return true;
                }
            }
        }

        return false;
    }

    private function configuredRoute(string $route, ?string $locale): string
    {
        $route = trim($route, '/');
        foreach (config('App')->pageSnapshotManifestRoutes as $configuredRoute) {
            if (trim((string) $configuredRoute, '/') === $route) {
                return trim((string) $configuredRoute, '/');
            }

            if ($locale !== null && $this->resolveRoute((string) $configuredRoute, $locale) === $route) {
                return trim((string) $configuredRoute, '/');
            }
        }

        return $route;
    }

    private function resolveRoute(string $configuredRoute, string $locale): string
    {
        $configuredRoute = trim($configuredRoute, '/');
        if ($configuredRoute === 'home') {
            return 'home';
        }

        return PublicPaths::routePath($configuredRoute, $locale)
            ?? $configuredRoute;
    }
}
