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
        return $this->requestForRoutes(
            locale: $locale,
            route: $route,
            configuredRoutes: config('App')->pageSnapshotManifestRoutes,
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
        );
    }

    /**
     * Build a delivery identity from an explicit route allow-list.
     *
     * BFF rollout routes are intentionally separate from snapshot warm-up
     * routes: an entry may be delivered synchronously before it is safe to
     * persist it as a public snapshot.
     *
     * @param list<string> $configuredRoutes
     * @param array<string, mixed> $query
     */
    public function requestForRoutes(
        string $locale,
        string $route,
        array $configuredRoutes,
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

        $resolvedRoute = $this->matchConfiguredRoute($route, $locale, $configuredRoutes);
        if ($resolvedRoute === null) {
            return null;
        }

        return $this->buildRequest(
            locale: $locale,
            route: $resolvedRoute,
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
        );
    }

    /**
     * Build the BFF delivery identity for an explicitly approved route or,
     * when enabled, for any localized public route.
     *
     * Only routes in the snapshot manifest are snapshot-eligible. This keeps
     * the full-site BFF cutover independent from snapshot storage growth while
     * preserving snapshot-first delivery for the bounded manifest.
     *
     * @param array<string, mixed> $query
     */
    public function requestForBff(
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

        $resolvedRoute = $config->pageDeliveryBffAllRoutes
            ? $route
            : $this->matchConfiguredRoute($route, $locale, $config->pageDeliveryBffRoutes);
        if ($resolvedRoute === null) {
            return null;
        }

        return new PageDeliveryRequest(
            locale: $locale,
            route: $resolvedRoute,
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
            snapshotEligible: $this->manifestContains($resolvedRoute, $locale),
            useBff: true,
        );
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

    /**
     * @param list<string> $configuredRoutes
     */
    private function matchConfiguredRoute(string $route, string $locale, array $configuredRoutes): ?string
    {
        foreach ($configuredRoutes as $configuredRoute) {
            $resolvedRoute = $this->resolveRoute((string) $configuredRoute, $locale);
            if ($resolvedRoute === $route) {
                return $resolvedRoute;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $query */
    private function buildRequest(
        string $locale,
        string $route,
        bool $preview,
        ?string $previewExpires,
        ?string $previewSignature,
        array $query,
    ): PageDeliveryRequest {
        return new PageDeliveryRequest(
            locale: $locale,
            route: $route,
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
        );
    }
}
