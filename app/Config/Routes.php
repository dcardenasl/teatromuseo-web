<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Health check endpoint (no locale prefix, excluded from localized routing)
$routes->get('health', 'HealthController::index', ['as' => 'health']);
$routes->get('diagnostics/public-read', 'DiagnosticsController::publicRead', [
    'as'     => 'diagnostics_public_read',
    'filter' => 'throttle:10,60',
]);
$routes->get('sitemap.xml', 'SitemapController::index', ['as' => 'sitemap']);

// Internal cache invalidation — no locale prefix, secured by X-Invalidate-Key header.
// Throttled: POST endpoints only, so crawlers on GET pages are never rate-limited.
$routes->post('cache/invalidate', 'CacheController::invalidate', ['as' => 'cache_invalidate', 'filter' => 'throttle:10,60']);
$routes->get('cache/status', 'CacheController::status', ['as' => 'cache_status', 'filter' => 'throttle:30,60']);

// Dynamic form submissions
$routes->post('forms/(:segment)/submit', 'FormController::submit/$1', [
    'as'     => 'form_submit',
    'filter' => ['csrf', 'throttle:10,60'],
]);

// Block Preview (called from admin panel) — throttled, plus an optional
// shared-secret check (X-Block-Preview-Key) enforced when BLOCK_PREVIEW_KEY
// is configured. See BlockPreviewController::hasValidPreviewKeyOrNoneConfigured().
$routes->post('blocks/preview', 'BlockPreviewController::preview', ['as' => 'blocks_preview', 'filter' => 'throttle:10,60']);

// Locales are compile-time application configuration. Adding a locale requires
// updating app/Config/App.php and deploying so the route table is rebuilt.

// Dynamic form submissions (localized)
$routes->post('{locale}/forms/(:segment)/submit', 'FormController::submit/$1', [
    'as'     => 'form_submit_localized',
    'filter' => ['csrf', 'throttle:10,60'],
]);

// Localized routes
$routes->get('{locale}', 'PageController::home', ['as' => 'home_localized']);
$routes->get('{locale}/sitemap.xml', 'SitemapController::index', ['as' => 'sitemap_localized']);

// Museum Catalog and Event / Shows routes — one route group per configured
// locale, each using that locale's own path segment (e.g. es/cartelera,
// en/programming, fr/programmation). CI4's `{locale}` placeholder can't vary the
// literal segment that follows it within a single route group, so each
// locale gets its own group instead; BaseController::initController() still
// derives the actual request locale from URL segment 1 regardless of which
// group matched, so this doesn't change locale resolution — only which
// literal segment is accepted per locale. Config\App::$supportedLocales is
// the same compile-time fallback list already used elsewhere in this file
// (see the note above) for exactly this reason: Routes.php runs once at
// boot, before any per-request CMS locale discovery.
foreach (config('App')->supportedLocales as $publicPathLocale) {
    // Route names are suffixed per locale: each loop iteration would
    // otherwise register the same name multiple times, and CI4 keeps only
    // the last registration for a given name.
    $routes->group($publicPathLocale . '/' . \App\Support\PublicPaths::catalogSegment($publicPathLocale), static function ($routes) use ($publicPathLocale): void {
        $routes->get('', '\App\Controllers\MuseumController::index', ['as' => 'museum_collection_' . $publicPathLocale]);
        $routes->get('(:any)', '\App\Controllers\MuseumController::show/$1', ['as' => 'museum_collection_detail_' . $publicPathLocale]);
    });

    $routes->group($publicPathLocale . '/' . \App\Support\PublicPaths::eventsSegment($publicPathLocale), static function ($routes) use ($publicPathLocale): void {
        $routes->get('', '\App\Controllers\EventController::index', ['as' => 'event_listing_' . $publicPathLocale]);
        $routes->get('(:any)', '\App\Controllers\EventController::show/$1', ['as' => 'event_show_detail_' . $publicPathLocale]);
    });
}

$routes->get('{locale}/(:any)', 'PageController::resolve/$1');

// Fallback non-localized routes (redirected/resolved dynamically)
$routes->get('/', 'PageController::home', ['as' => 'home']);
$routes->get('(:any)', 'PageController::resolve/$1');
