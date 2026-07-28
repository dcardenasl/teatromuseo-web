<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Health check endpoint (no locale prefix, excluded from localized routing)
$routes->get('health', 'HealthController::index', ['as' => 'health']);
$routes->get('sitemap.xml', 'SitemapController::index', ['as' => 'sitemap']);

// Internal cache invalidation — no locale prefix, secured by X-Invalidate-Key header.
// Throttled: POST endpoints only, so crawlers on GET pages are never rate-limited.
$routes->post('cache/invalidate', 'CacheController::invalidate', ['as' => 'cache_invalidate', 'filter' => 'throttle:10,60']);

// Dynamic form submissions
$routes->post('forms/(:segment)/submit', 'FormController::submit/$1', ['as' => 'form_submit', 'filter' => 'throttle:10,60']);

// Block Preview (called from admin panel) — unauthenticated, so throttled like
// the other public POST routes above.
$routes->post('blocks/preview', 'BlockPreviewController::preview', ['as' => 'blocks_preview', 'filter' => 'throttle:10,60']);

// Locale validity is resolved from the CMS/API during controller bootstrap.
// Config\App::$supportedLocales remains a fallback for API outages.

// Dynamic form submissions (localized)
$routes->post('{locale}/forms/(:segment)/submit', 'FormController::submit/$1', ['as' => 'form_submit_localized', 'filter' => 'throttle:10,60']);

// Localized routes
$routes->get('{locale}', 'PageController::home', ['as' => 'home_localized']);
$routes->get('{locale}/sitemap.xml', 'SitemapController::index', ['as' => 'sitemap_localized']);

// Museum Catalog routes
$routes->group('{locale}/museo', static function ($routes): void {
    $routes->get('coleccion', '\App\Controllers\MuseumController::index', ['as' => 'museum_collection']);
    $routes->get('coleccion/(:any)', '\App\Controllers\MuseumController::show/$1', ['as' => 'museum_collection_detail']);
});

$routes->get('{locale}/(:any)', 'PageController::resolve/$1');

// Fallback non-localized routes (redirected/resolved dynamically)
$routes->get('/', 'PageController::home', ['as' => 'home']);
$routes->get('(:any)', 'PageController::resolve/$1');
