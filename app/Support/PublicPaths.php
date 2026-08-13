<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized constants for public routing paths.
 *
 * `EVENTS`/`CATALOG` are the invariant Spanish identifiers used to look up
 * the CMS-owned index page for each section (see
 * BasePublicWebController::renderCmsPageOrFallbackListing()). That lookup is
 * a content key, not a URL — it stays fixed across locales on purpose.
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

        $aliases = ['home', 'inicio', 'accueil'];
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
     * Turn a domain redirect record ({new_url, redirect_type}) into an
     * absolute-or-relative target path and its HTTP status.
     *
     * Shared by the legacy resolver (`PageController::resolve()`) and
     * PageDelivery (`SynchronousPageDeliveryAdapter`) so both honor the same
     * external-vs-internal detection and locale-aware canonical
     * normalization — one implementation, not two that can drift apart.
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

        $aliases = [
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

        $routeKey = $aliases[$normalized] ?? null;

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
