<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Base Site URL
     * --------------------------------------------------------------------------
     *
     * URL to your CodeIgniter root. Typically, this will be your base URL,
     * WITH a trailing slash:
     *
     * E.g., http://example.com/
     */
    public string $baseURL = 'http://localhost:8184/';

    /**
     * Allowed Hostnames in the Site URL other than the hostname in the baseURL.
     * If you want to accept multiple Hostnames, set this.
     *
     * E.g.,
     * When your site URL ($baseURL) is 'http://example.com/', and your site
     * also accepts 'http://media.example.com/' and 'http://accounts.example.com/':
     *     ['media.example.com', 'accounts.example.com']
     *
     * @var list<string>
     */
    public array $allowedHostnames = [];

    /**
     * --------------------------------------------------------------------------
     * Index File
     * --------------------------------------------------------------------------
     *
     * Typically, this will be your `index.php` file, unless you've renamed it to
     * something else. If you have configured your web server to remove this file
     * from your site URIs, set this variable to an empty string.
     */
    public string $indexPage = '';

    /**
     * --------------------------------------------------------------------------
     * URI PROTOCOL
     * --------------------------------------------------------------------------
     *
     * This item determines which server global should be used to retrieve the
     * URI string. The default setting of 'REQUEST_URI' works for most servers.
     * If your links do not seem to work, try one of the other delicious flavors:
     *
     *  'REQUEST_URI': Uses $_SERVER['REQUEST_URI']
     * 'QUERY_STRING': Uses $_SERVER['QUERY_STRING']
     *    'PATH_INFO': Uses $_SERVER['PATH_INFO']
     *
     * WARNING: If you set this to 'PATH_INFO', URIs will always be URL-decoded!
     */
    public string $uriProtocol = 'REQUEST_URI';

    /*
    |--------------------------------------------------------------------------
    | Allowed URL Characters
    |--------------------------------------------------------------------------
    |
    | This lets you specify which characters are permitted within your URLs.
    | When someone tries to submit a URL with disallowed characters they will
    | get a warning message.
    |
    | As a security measure you are STRONGLY encouraged to restrict URLs to
    | as few characters as possible.
    |
    | By default, only these are allowed: `a-z 0-9~%.:_-`
    |
    | Set an empty string to allow all characters -- but only if you are insane.
    |
    | The configured value is actually a regular expression character group
    | and it will be used as: '/\A[<permittedURIChars>]+\z/iu'
    |
    | DO NOT CHANGE THIS UNLESS YOU FULLY UNDERSTAND THE REPERCUSSIONS!!
    |
    */
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    /**
     * --------------------------------------------------------------------------
     * Default Locale
     * --------------------------------------------------------------------------
     *
     * The Locale roughly represents the language and location that your visitor
     * is viewing the site from. It affects the language strings and other
     * strings (like currency markers, numbers, etc), that your program
     * should run under for this request.
     */
    public string $defaultLocale = 'es';

    /**
     * --------------------------------------------------------------------------
     * Negotiate Locale
     * --------------------------------------------------------------------------
     *
     * If true, the current Request object will automatically determine the
     * language to use based on the value of the Accept-Language header.
     *
     * If false, no automatic detection will be performed.
     */
    // The public URL is the locale contract (/es, /en, /fr, ...). Browser
    // Accept-Language must never override an explicit locale in the URL.
    // The active locale list is discovered from the CMS during bootstrap and
    // applied by BaseController before any public content is rendered.
    public bool $negotiateLocale = false;

    /**
     * --------------------------------------------------------------------------
     * Supported Locales
     * --------------------------------------------------------------------------
     *
     * If $negotiateLocale is true, this array lists the locales supported
     * by the application in descending order of priority. If no match is
     * found, the first locale will be used.
     *
     * IncomingRequest::setLocale() also uses this list.
     *
     * @var list<string>
     */
    public array $supportedLocales = ['es', 'en', 'fr', 'pt'];

    /**
     * --------------------------------------------------------------------------
     * Application Timezone
     * --------------------------------------------------------------------------
     *
     * The default timezone that will be used in your application to display
     * dates with the date helper, and can be retrieved through app_timezone()
     *
     * @see https://www.php.net/manual/en/timezones.php for list of timezones
     *      supported by PHP.
     */
    public string $appTimezone = 'UTC';

    /**
     * --------------------------------------------------------------------------
     * Default Character Set
     * --------------------------------------------------------------------------
     *
     * This determines which character set is used by default in various methods
     * that require a character set to be provided.
     *
     * @see http://php.net/htmlspecialchars for a list of supported charsets.
     */
    public string $charset = 'UTF-8';

    /**
     * --------------------------------------------------------------------------
     * Website Builder API Configuration
     * --------------------------------------------------------------------------
     *
     * Configuration for BFF public reads and the separate analytics write
     * surface. Public reads use only the BFF settings below; analytics keeps
     * its own CMS write endpoint and key.
     */
    public string $trackingApiBaseUrl = '';
    public string $webApiKey = '';

    /** Base URL for the direct public-read BFF. */
    public string $bffApiBaseUrl = 'http://localhost:8188';

    /** Shared application key registered for the BFF public-read surface. */
    public string $bffApiKey = '';

    /**
     * Timeout (seconds) for requests against the Domain API.
     * Override with WEB_API_TIMEOUT in .env. Two seconds keeps one slow
     * optional block from holding a shared-hosting worker for the full page.
     */
    public int $webApiTimeout = 2;

    /**
     * Connection-establishment timeout (seconds) for Domain API requests.
     * A slow connection must not hold a shared-hosting worker for the full
     * response deadline. Override with WEB_API_CONNECT_TIMEOUT in .env.
     */
    public int $webApiConnectTimeout = 1;

    /**
     * TTL (seconds) for the long-lived stale cache copy served when the
     * Domain API is down. Set WEB_API_STALE_TTL=0 in .env to disable.
     */
    public int $webApiStaleTtl = 86400;

    /**
     * Maximum number of simultaneous Domain API calls from one page render.
     * Shared hosting commonly imposes a low per-account process/connection
     * ceiling; one concurrent call is the safe default for this shared host
     * and prevents a cache miss from producing provider-level 508s.
     * Override with WEB_API_MAX_PARALLEL_REQUESTS in .env.
     */
    public int $webApiMaxParallelRequests = 1;

    /**
     * First-party page-view tracking is disabled in production by default.
     * Tracking is best-effort and must never compete with public delivery for
     * a PHP worker or database connection.
     */
    public bool $trackingEnabled = ENVIRONMENT !== 'production';

    /** Directory where page-view events wait for the analytics cron worker. */
    public string $analyticsQueueDirectory = WRITEPATH . 'analytics-queue';

    /** Maximum queued events processed by one cron invocation. */
    public int $trackingQueueBatchSize = 100;

    /** Number of delivery attempts before an event is quarantined. */
    public int $trackingQueueMaxAttempts = 5;

    /** CLI transport timeouts; these never run during a visitor request. */
    public int $trackingQueueTimeoutMs = 5000;
    public int $trackingQueueConnectTimeoutMs = 1000;

    /**
     * TTL (seconds) for full HTML response caching on public pages.
     *
     * Keep this disabled outside production so feature tests and local
     * development always render against the current upstream data.
     * Override with WEB_PAGE_CACHE_TTL in .env.
     */
    public int $webPageCacheTtl = ENVIRONMENT === 'production' ? 300 : 0;

    /**
     * PageDelivery remains opt-in until the shared snapshot backend and load
     * budget have been verified. When enabled, snapshot mode is snapshot-first.
     */
    public bool $pageDeliveryEnabled = false;
    public string $pageDeliveryMode = 'snapshot';
    public bool $pageDeliveryAllowSynchronousFallback = false;

    /**
     * Explicit routes already verified against the BFF's full-page resolver.
     * This is independent from snapshot warm-up so a route can roll out in
     * synchronous mode before it is eligible for persisted snapshots.
     *
     * @var list<string>
     */
    public array $pageDeliveryBffRoutes = ['home'];

    public string $pageSnapshotDirectory = '';
    public int $pageSnapshotStaleTtl = 86400;
    public int $pageSnapshotTtl = 300;
    public int $pageSnapshotMaxBytes = 5242880;
    public int $pageSnapshotRetention = 3;
    public int $pageSnapshotLockTtl = 900;
    public string $pageSnapshotCompression = 'gzip';
    public bool $pageSnapshotShared = false;

    /**
     * Explicit route keys warmed by deploy/cron. `events` and `catalog` are
     * resolved to their locale-specific public paths by PublicSnapshotManifest.
     * CMS slugs must be appended explicitly in the deployment environment.
     *
     * @var list<string>
     */
    public array $pageSnapshotManifestRoutes = ['home', 'events', 'catalog'];

    /** @var list<string> */
    public array $pageSnapshotScopes = [
        'settings',
        'menus',
        'pages',
        'collections',
        'entries',
        'taxonomies',
        'events',
        'event_types',
        'categories',
        'techniques',
        'collection_items',
        'redirects',
        'forms',
    ];

    /**
     * --------------------------------------------------------------------------
     * Force Global Secure Requests
     * --------------------------------------------------------------------------
     *
     * If true, this will force every request made to this application to be
     * made via a secure connection (HTTPS). If the incoming request is not
     * secure, the user will be redirected to a secure version of the page
     * and the HTTP Strict Transport Security (HSTS) header will be set.
     */
    public bool $forceGlobalSecureRequests = ENVIRONMENT === 'production';

    /**
     * --------------------------------------------------------------------------
     * Reverse Proxy IPs
     * --------------------------------------------------------------------------
     *
     * If your server is behind a reverse proxy, you must whitelist the proxy
     * IP addresses from which CodeIgniter should trust headers such as
     * X-Forwarded-For or Client-IP in order to properly identify
     * the visitor's IP address.
     *
     * You need to set a proxy IP address or IP address with subnets and
     * the HTTP header for the client IP address.
     *
     * Here are some examples:
     *     [
     *         '10.0.1.200'     => 'X-Forwarded-For',
     *         '192.168.5.0/24' => 'X-Real-IP',
     *     ]
     *
     * @var array<string, string>
     */
    public array $proxyIPs = [];

    /**
     * --------------------------------------------------------------------------
     * Content Security Policy
     * --------------------------------------------------------------------------
     *
     * Enables the Response's Content Secure Policy to restrict the sources that
     * can be used for images, scripts, CSS files, audio, video, etc. If enabled,
     * the Response object will populate default values for the policy from the
     * `ContentSecurityPolicy.php` file. Controllers can always add to those
     * restrictions at run time.
     *
     * For a better understanding of CSP, see these documents:
     *
     * @see http://www.html5rocks.com/en/tutorials/security/content-security-policy/
     * @see http://www.w3.org/TR/CSP/
     */
    public bool $CSPEnabled = ENVIRONMENT === 'production';

    /**
     * --------------------------------------------------------------------------
     * Content-Security-Policy source allowlists (SecurityHeadersFilter)
     * --------------------------------------------------------------------------
     *
     * Space/comma-separated source lists for the custom CSP header built in
     * App\Filters\SecurityHeadersFilter (kept separate from CI4's native CSP,
     * see that filter's docblock). Override via CSP_OBJECT_SRC / CSP_IMAGE_SRC /
     * CSP_FRAME_SRC / CSP_MEDIA_SRC in .env to tighten the allowlist for
     * production; defaults stay permissive to keep the starter working with
     * seeded remote media out of the box.
     *
     * @var list<string>
     */
    public array $cspObjectSrc = ['self', 'http:', 'https:'];

    /** @var list<string> */
    public array $cspImageSrc = ['self', 'http:', 'https:', 'data:'];

    /** @var list<string> */
    public array $cspFrameSrc = ['self', 'http:', 'https:'];

    /** @var list<string> */
    public array $cspMediaSrc = ['self', 'http:', 'https:'];

    /**
     * Opt-in flag so ThrottleFilterTest can exercise real throttling behavior
     * (throttling is otherwise bypassed under ENVIRONMENT=testing so feature
     * tests hitting controllers directly are never penalized). Read fresh via
     * `new Config\App()` in ThrottleFilter rather than the shared `config('App')`
     * instance, since tests toggle WEB_THROTTLE_IN_TESTS per-test at runtime.
     */
    public bool $throttleInTestsEnabled = false;

    public function __construct()
    {
        parent::__construct();

        // Validate baseURL is configured. In production, the hardcoded localhost
        // default must never be used silently — require an explicit app.baseURL in
        // .env. In development the localhost fallback is acceptable.
        $baseUrlFromEnv = (string) (env('app.baseURL') ?? '');
        if ($this->baseURL === '' || (ENVIRONMENT === 'production' && trim($baseUrlFromEnv) === '')) {
            throw new \LogicException(
                'Missing app.baseURL in .env. '
                . 'Set app.baseURL to your website URL. '
                . 'Example: app.baseURL=http://localhost:8184/'
            );
        }

        // Load the separate analytics write endpoint from .env.
        $trackingApiBaseUrl = env('WEB_TRACKING_API_BASE_URL');
        if (! is_string($trackingApiBaseUrl) || trim($trackingApiBaseUrl) === '') {
            throw new \LogicException(
                'Missing WEB_TRACKING_API_BASE_URL in .env. '
                . 'Set WEB_TRACKING_API_BASE_URL to the CMS write endpoint. '
                . 'Example: WEB_TRACKING_API_BASE_URL=http://localhost:8190'
            );
        }
        $this->trackingApiBaseUrl = rtrim($trackingApiBaseUrl, '/');

        $webApiKey = env('WEB_API_KEY');
        if (! is_string($webApiKey) || trim($webApiKey) === '') {
            throw new \LogicException(
                'Missing WEB_API_KEY in .env. '
                . 'This is the API key registered in your domain API. '
                . 'Contact your administrator for the key.'
            );
        }
        $this->webApiKey = $webApiKey;

        $bffApiBaseUrl = env('BFF_API_BASE_URL') ?: $this->bffApiBaseUrl;
        $this->bffApiBaseUrl = rtrim((string) $bffApiBaseUrl, '/');

        $bffApiKey = env('BFF_API_KEY') ?: $webApiKey;
        if (! is_string($bffApiKey) || trim($bffApiKey) === '') {
            throw new \LogicException(
                'Missing BFF_API_KEY in .env. Set the application key registered in the BFF.'
            );
        }
        $this->bffApiKey = $bffApiKey;

        // Optional tuning knobs — silently keep defaults when absent.
        $webApiTimeout = env('WEB_API_TIMEOUT');
        if (is_numeric($webApiTimeout) && (int) $webApiTimeout > 0) {
            $this->webApiTimeout = (int) $webApiTimeout;
        }

        $webApiConnectTimeout = env('WEB_API_CONNECT_TIMEOUT');
        if (is_numeric($webApiConnectTimeout) && (int) $webApiConnectTimeout > 0) {
            $this->webApiConnectTimeout = min($this->webApiTimeout, (int) $webApiConnectTimeout);
        }

        $webApiStaleTtl = env('WEB_API_STALE_TTL');
        if (is_numeric($webApiStaleTtl) && (int) $webApiStaleTtl >= 0) {
            $this->webApiStaleTtl = (int) $webApiStaleTtl;
        }

        $webApiMaxParallelRequests = env('WEB_API_MAX_PARALLEL_REQUESTS');
        if (is_numeric($webApiMaxParallelRequests) && (int) $webApiMaxParallelRequests > 0) {
            // Production must stay within the shared-hosting budget even if a
            // stale or mistyped .env requests a larger burst. Non-production
            // environments may opt into wider concurrency for load tests.
            // One remains the safe default. The production ceiling is two so
            // beta can opt into the documented QA-03 calibration explicitly;
            // no deployment receives two concurrent calls unless its .env
            // asks for it and the hosting budget has been observed.
            $maximumParallelRequests = ENVIRONMENT === 'production' ? 2 : 16;
            $this->webApiMaxParallelRequests = min(
                $maximumParallelRequests,
                (int) $webApiMaxParallelRequests,
            );
        }

        $this->trackingEnabled = $this->parseBoolean(
            env('WEB_TRACKING_ENABLED'),
            $this->trackingEnabled,
        );

        $trackingQueueDirectory = env('WEB_TRACKING_QUEUE_DIR');
        if (is_string($trackingQueueDirectory) && trim($trackingQueueDirectory) !== '') {
            $trackingQueueDirectory = trim($trackingQueueDirectory);
            $this->analyticsQueueDirectory = str_starts_with($trackingQueueDirectory, DIRECTORY_SEPARATOR)
                ? rtrim($trackingQueueDirectory, DIRECTORY_SEPARATOR)
                : ROOTPATH . trim($trackingQueueDirectory, " /\\");
        }

        $trackingQueueBatchSize = env('WEB_TRACKING_QUEUE_BATCH_SIZE');
        if (is_numeric($trackingQueueBatchSize) && (int) $trackingQueueBatchSize > 0) {
            $this->trackingQueueBatchSize = min(500, (int) $trackingQueueBatchSize);
        }

        $trackingQueueMaxAttempts = env('WEB_TRACKING_QUEUE_MAX_ATTEMPTS');
        if (is_numeric($trackingQueueMaxAttempts) && (int) $trackingQueueMaxAttempts > 0) {
            $this->trackingQueueMaxAttempts = min(20, (int) $trackingQueueMaxAttempts);
        }

        $trackingQueueTimeout = env('WEB_TRACKING_QUEUE_TIMEOUT_MS');
        if (is_numeric($trackingQueueTimeout) && (int) $trackingQueueTimeout > 0) {
            $this->trackingQueueTimeoutMs = min(30000, (int) $trackingQueueTimeout);
        }

        $trackingQueueConnectTimeout = env('WEB_TRACKING_QUEUE_CONNECT_TIMEOUT_MS');
        if (is_numeric($trackingQueueConnectTimeout) && (int) $trackingQueueConnectTimeout > 0) {
            $this->trackingQueueConnectTimeoutMs = min(
                $this->trackingQueueTimeoutMs,
                (int) $trackingQueueConnectTimeout,
            );
        }

        $webPageCacheTtl = env('WEB_PAGE_CACHE_TTL');
        if (is_numeric($webPageCacheTtl) && (int) $webPageCacheTtl >= 0) {
            $this->webPageCacheTtl = (int) $webPageCacheTtl;
        }

        $this->pageDeliveryEnabled = $this->parseBoolean(env('WEB_PAGE_DELIVERY_ENABLED'), false);
        $pageDeliveryMode = strtolower(trim((string) (env('WEB_PAGE_DELIVERY_MODE') ?? '')));
        if (in_array($pageDeliveryMode, ['snapshot', 'sync'], true)) {
            $this->pageDeliveryMode = $pageDeliveryMode;
        }
        $this->pageDeliveryAllowSynchronousFallback = $this->parseBoolean(
            env('WEB_PAGE_DELIVERY_ALLOW_SYNC_FALLBACK'),
            false,
        );
        $bffRoutes = env('WEB_PAGE_DELIVERY_BFF_ROUTES');
        if (is_string($bffRoutes) && trim($bffRoutes) !== '') {
            $routes = array_values(array_filter(
                array_map(static fn (string $route): string => trim($route, " /\t\n\r\0\x0B"), explode(',', $bffRoutes)),
                static fn (string $route): bool => $route !== '',
            ));
            if ($routes !== []) {
                $this->pageDeliveryBffRoutes = $routes;
            }
        }
        $pageSnapshotDirectory = env('WEB_PAGE_SNAPSHOT_DIR');
        if (is_string($pageSnapshotDirectory) && trim($pageSnapshotDirectory) !== '') {
            $this->pageSnapshotDirectory = rtrim(trim($pageSnapshotDirectory), DIRECTORY_SEPARATOR);
        }
        $pageSnapshotStaleTtl = env('WEB_PAGE_SNAPSHOT_STALE_TTL');
        if (is_numeric($pageSnapshotStaleTtl) && (int) $pageSnapshotStaleTtl >= 0) {
            $this->pageSnapshotStaleTtl = (int) $pageSnapshotStaleTtl;
        }
        $pageSnapshotTtl = env('WEB_PAGE_SNAPSHOT_TTL');
        if (is_numeric($pageSnapshotTtl) && (int) $pageSnapshotTtl > 0) {
            $this->pageSnapshotTtl = (int) $pageSnapshotTtl;
        }
        $pageSnapshotMaxBytes = env('WEB_PAGE_SNAPSHOT_MAX_BYTES');
        if (is_numeric($pageSnapshotMaxBytes) && (int) $pageSnapshotMaxBytes >= 131072) {
            $this->pageSnapshotMaxBytes = min(50 * 1024 * 1024, (int) $pageSnapshotMaxBytes);
        }
        $pageSnapshotRetention = env('WEB_PAGE_SNAPSHOT_RETENTION');
        if (is_numeric($pageSnapshotRetention) && (int) $pageSnapshotRetention > 0) {
            $this->pageSnapshotRetention = min(10, (int) $pageSnapshotRetention);
        }
        $pageSnapshotLockTtl = env('WEB_PAGE_SNAPSHOT_LOCK_TTL');
        if (is_numeric($pageSnapshotLockTtl) && (int) $pageSnapshotLockTtl > 0) {
            $this->pageSnapshotLockTtl = min(3600, (int) $pageSnapshotLockTtl);
        }
        $pageSnapshotCompression = strtolower(trim((string) (env('WEB_PAGE_SNAPSHOT_COMPRESSION') ?? '')));
        if (in_array($pageSnapshotCompression, ['gzip', 'none'], true)) {
            $this->pageSnapshotCompression = $pageSnapshotCompression;
        }
        $this->pageSnapshotShared = $this->parseBoolean(env('WEB_PAGE_SNAPSHOT_SHARED'), false);

        $manifestRoutes = env('WEB_PAGE_SNAPSHOT_MANIFEST_ROUTES');
        if (is_string($manifestRoutes) && trim($manifestRoutes) !== '') {
            $routes = array_values(array_filter(
                array_map(static fn (string $route): string => trim($route, " /\t\n\r\0\x0B"), explode(',', $manifestRoutes)),
                static fn (string $route): bool => $route !== '',
            ));
            if ($routes !== []) {
                $this->pageSnapshotManifestRoutes = $routes;
            }
        }

        $this->cspObjectSrc = $this->parseCspSources(env('CSP_OBJECT_SRC'), $this->cspObjectSrc);
        $this->cspImageSrc  = $this->parseCspSources(env('CSP_IMAGE_SRC'), $this->cspImageSrc);
        $this->cspFrameSrc  = $this->parseCspSources(env('CSP_FRAME_SRC'), $this->cspFrameSrc);
        $this->cspMediaSrc  = $this->parseCspSources(env('CSP_MEDIA_SRC'), $this->cspMediaSrc);

        $throttleInTests = env('WEB_THROTTLE_IN_TESTS');
        $this->throttleInTestsEnabled = $throttleInTests === true || $throttleInTests === 'true';
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private function parseCspSources(mixed $raw, array $default): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return $default;
        }

        $sources = preg_split('/[\s,]+/', trim($raw)) ?: [];

        return $sources !== [] ? $sources : $default;
    }

    private function parseBoolean(mixed $raw, bool $default): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (! is_string($raw)) {
            return $default;
        }

        return match (strtolower(trim($raw))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}
