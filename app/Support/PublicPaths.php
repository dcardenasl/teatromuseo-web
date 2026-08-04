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
        'en' => 'events',
        'fr' => 'programme',
        'pt' => 'eventos',
    ];

    /** @var array<string, string> */
    private const CATALOG_SEGMENTS = [
        'es' => self::CATALOG,
        'en' => 'museum/collection',
        'fr' => 'musee/collection',
        'pt' => 'museu/colecao',
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
            default => null,
        };
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
