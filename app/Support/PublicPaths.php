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

        if (in_array(strtolower($normalized), ['home', 'inicio'], true)) {
            return '/';
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
        $localeSegment = strtolower(trim($locale, '/'));
        if (strtolower($normalized) === $localeSegment) {
            return '/';
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
