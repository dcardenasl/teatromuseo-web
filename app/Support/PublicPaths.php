<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized constants for public routing paths.
 *
 * `EVENTS`/`CATALOG` are the invariant Spanish route keys used by the public
 * controllers when building BFF page-resolve requests. They stay fixed across
 * locales; the visitor-facing URL segment is locale-aware below.
 *
 * The actual public URL segment (what a visitor types/sees) is locale-aware
 * and comes from `eventsSegment()`/`catalogSegment()` below. New locales
 * fall back to the Spanish segment until translated here — same fallback
 * shape as `Config\App::$supportedLocales`, which this list mirrors.
 */
class PublicPaths
{
    public const EVENTS = 'cartelera';
    public const CATALOG = 'museo/coleccion';

    /** @var array<string, string> */
    private const HOMEPAGE_SEGMENTS = [
        'es' => 'inicio',
        'en' => 'home',
        'fr' => 'accueil',
        'pt' => 'inicio',
    ];

    /** @var array<string, string> */
    private const EVENTS_SEGMENTS = [
        'es' => self::EVENTS,
        'en' => 'programming',
        'fr' => 'programmation',
        'pt' => 'programacao',
    ];

    /** @var array<string, string> */
    private const CATALOG_SEGMENTS = [
        'es' => self::CATALOG,
        'en' => 'museum/collection',
        'fr' => 'musee/collection',
        'pt' => 'museu/colecao',
    ];

    /** @var list<string> */
    private const HOMEPAGE_ALIASES = ['home', 'inicio', 'accueil'];

    /** @var array<string, string> */
    private const ROUTE_ALIASES = [
        'cartelera' => 'events',
        'events' => 'events',
        'programme' => 'events',
        'eventos' => 'events',
        'programming' => 'events',
        'programmation' => 'events',
        'programacao' => 'events',
        'museo/coleccion' => 'catalog',
        'museum/collection' => 'catalog',
        'musee/collection' => 'catalog',
        'museu/colecao' => 'catalog',
        'contacto' => 'contact',
        'contact' => 'contact',
        'contato' => 'contact',
        'historia' => 'history',
        'history' => 'history',
        'histoire' => 'history',
        'nossa-historia' => 'history',
        'cursos' => 'theatre_school',
        'teatroescuela' => 'theatre_school',
        'theaterschool' => 'theatre_school',
        'theatreecole' => 'theatre_school',
        'escola-de-teatro' => 'theatre_school',
    ];

    /** @var array<string, string> */
    private const CONTACT_SEGMENTS = [
        'es' => 'contacto',
        'en' => 'contact',
        'fr' => 'contact',
        'pt' => 'contato',
    ];

    /** @var array<string, string> */
    private const HISTORY_SEGMENTS = [
        'es' => 'historia',
        'en' => 'history',
        'fr' => 'histoire',
        'pt' => 'nossa-historia',
    ];

    /** @var array<string, string> */
    private const THEATRE_SCHOOL_SEGMENTS = [
        'es' => 'teatroescuela',
        'en' => 'theaterschool',
        'fr' => 'theatreecole',
        'pt' => 'escola-de-teatro',
    ];

    public static function eventsSegment(string $locale): string
    {
        return self::EVENTS_SEGMENTS[$locale] ?? self::EVENTS;
    }

    public static function catalogSegment(string $locale): string
    {
        return self::CATALOG_SEGMENTS[$locale] ?? self::CATALOG;
    }

    public static function homepageSegment(string $locale): string
    {
        return self::HOMEPAGE_SEGMENTS[strtolower(trim($locale))] ?? 'inicio';
    }

    public static function homepagePath(string $locale): string
    {
        return '/' . self::homepageSegment($locale);
    }

    public static function isHomepageSlug(string $slug, ?string $locale = null): bool
    {
        $normalized = strtolower(trim($slug, '/'));
        if ($normalized === '') {
            return false;
        }

        $aliases = self::HOMEPAGE_ALIASES;
        if ($locale !== null) {
            $aliases[] = self::homepageSegment($locale);
        }

        return in_array($normalized, array_values(array_unique($aliases)), true);
    }

    public static function routePath(string $routeKey, string $locale): ?string
    {
        return match ($routeKey) {
            'events' => self::eventsSegment($locale),
            'catalog' => self::catalogSegment($locale),
            'contact' => self::CONTACT_SEGMENTS[$locale] ?? self::CONTACT_SEGMENTS['es'],
            'history' => self::HISTORY_SEGMENTS[$locale] ?? self::HISTORY_SEGMENTS['es'],
            'theatre_school' => self::THEATRE_SCHOOL_SEGMENTS[$locale] ?? self::THEATRE_SCHOOL_SEGMENTS['es'],
            default => null,
        };
    }

    /**
     * Export the versioned, framework-free route contract consumed by the
     * cross-repository CI parity check.
     *
     * Web remains the owner of the visitor-facing policy. The BFF keeps an
     * independent read-only adapter because it must resolve incoming paths;
     * the exported contract prevents those adapters from drifting silently.
     *
     * @return array<string, mixed>
     */
    public static function publicRouteContract(): array
    {
        $locales = ['es', 'en', 'fr', 'pt'];
        $routeKeys = ['events', 'catalog', 'contact', 'history', 'theatre_school'];
        $routes = ['homepage' => []];

        foreach ($locales as $locale) {
            $routes['homepage'][$locale] = self::homepageSegment($locale);
        }
        foreach ($routeKeys as $routeKey) {
            $routes[$routeKey] = [];
            foreach ($locales as $locale) {
                $routes[$routeKey][$locale] = self::routePath($routeKey, $locale);
            }
        }

        $aliases = ['homepage' => self::HOMEPAGE_ALIASES];
        foreach (self::ROUTE_ALIASES as $alias => $routeKey) {
            $aliases[$routeKey][] = $alias;
        }
        foreach ($aliases as &$routeAliases) {
            sort($routeAliases);
        }
        unset($routeAliases);

        return [
            'version' => 1,
            'locales' => $locales,
            'routes' => $routes,
            'aliases' => $aliases,
        ];
    }

    /**
     * Turn a domain redirect record ({new_url, redirect_type}) into an
     * absolute-or-relative target path and its HTTP status.
     *
     * Shared by the Web URL policy and PageDelivery so redirects received in
     * the BFF envelope use one external-vs-internal and locale-aware
     * normalization implementation.
     *
     * @param array<string, mixed> $redirect
     * @return array{path: string, status: int}
     */
    public static function resolveRedirectTarget(array $redirect, string $locale): array
    {
        $status = match ($redirect['redirect_type'] ?? 'permanent') {
            'temporary' => 302,
            default => 301,
        };

        $redirectPath = (string) ($redirect['new_url'] ?? '');
        $parsed = parse_url(trim($redirectPath));
        $isExternal = is_array($parsed)
            && (($parsed['scheme'] ?? '') !== '' || ($parsed['host'] ?? '') !== '');

        if (! $isExternal) {
            $canonicalPath = self::canonicalPath($redirectPath, $locale);
            if ($canonicalPath !== null) {
                $redirectPath = $canonicalPath;
            }
        }

        return ['path' => $redirectPath, 'status' => $status];
    }

    /**
     * Resolve known legacy/editorial aliases to the canonical public path.
     *
     * CMS URLs are authored without a locale prefix. This keeps old content
     * valid after localized public slugs change, while unknown custom URLs
     * continue through the normal URL handling path.
     */
    public static function canonicalPath(string $path, string $locale): ?string
    {
        $normalized = trim((string) (parse_url(trim($path), PHP_URL_PATH) ?? ''), '/');

        if ($normalized === '') {
            return '/';
        }

        if (self::isHomepageSlug($normalized, $locale)) {
            return self::homepagePath($locale);
        }

        $routeKey = self::ROUTE_ALIASES[$normalized] ?? null;

        return $routeKey !== null ? '/' . self::routePath($routeKey, $locale) : null;
    }

    /**
     * Normalize a known internal URL to the locale-less path expected by
     * lang_url(). Unknown paths and external URLs are deliberately ignored so
     * editorial custom links retain their original destination.
     */
    public static function normalizeLocalizedPath(string $path, string $locale): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        $parsed = parse_url($trimmed);
        if (! is_array($parsed) || ($parsed['scheme'] ?? '') !== '' || ($parsed['host'] ?? '') !== '') {
            return null;
        }

        if (($parsed['query'] ?? '') !== '' || ($parsed['fragment'] ?? '') !== '') {
            return null;
        }

        $normalized = trim((string) ($parsed['path'] ?? ''), '/');
        if ($normalized === '') {
            return self::homepagePath($locale);
        }

        $localeSegment = strtolower(trim($locale, '/'));
        if (strtolower($normalized) === $localeSegment) {
            return self::homepagePath($locale);
        }

        $localePrefix = $localeSegment . '/';
        if (str_starts_with(strtolower($normalized), $localePrefix)) {
            $normalized = substr($normalized, strlen($localePrefix));
        }

        return self::canonicalPath('/' . $normalized, $locale);
    }

    /** @return array<string, string> */
    public static function eventsSegments(): array
    {
        return self::EVENTS_SEGMENTS;
    }

    /** @return array<string, string> */
    public static function catalogSegments(): array
    {
        return self::CATALOG_SEGMENTS;
    }

}
