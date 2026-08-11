<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'           => CSRF::class,
        'toolbar'        => DebugToolbar::class,
        'honeypot'       => Honeypot::class,
        'invalidchars'   => InvalidChars::class,
        'secureheaders'  => SecureHeaders::class,
        'securityheaders' => \App\Filters\SecurityHeadersFilter::class,
        'tracking'        => \App\Filters\TrackingFilter::class,
        'throttle'        => \App\Filters\ThrottleFilter::class,
        'cors'           => Cors::class,
        'forcehttps'     => ForceHTTPS::class,
        'pagecache'      => PageCache::class,
        'performance'    => PerformanceMetrics::class,
        'correlationid'   => \App\Filters\CorrelationIdFilter::class,
        'requestTelemetry' => \App\Filters\RequestTelemetryFilter::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            // These must run before PageCache so a cache hit receives a fresh
            // request ID and emits telemetry instead of replaying headers
            // from the request that populated the cached response.
            'correlationid',
            'requestTelemetry',
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'securityheaders',
            'pagecache',   // Web Page Caching
            'requestTelemetry',
            'correlationid',
            'performance', // Performance Metrics
            // 'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#globals
     */
    public array $globals = [
        'before' => [
            'invalidchars', // Filter invalid/malicious characters from requests
        ],
        'after' => [
            'secureheaders',   // CI4 native: emits headers from Config\Security::$secureHeaders
            'tracking',        // First-party page-view tracking (fire-and-forget to Domain CMS)
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
