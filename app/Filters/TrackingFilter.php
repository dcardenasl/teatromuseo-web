<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

/**
 * TrackingFilter — queues a page-view event after every successful page
 * render. The request only performs a local atomic file write; the CMS call
 * runs later from `php spark analytics:flush`.
 */
class TrackingFilter implements FilterInterface
{
    private const BOT_PATTERNS = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
        'crawler', 'spider', '/bot', 'semrushbot', 'ahrefsbot',
        'petalbot', 'dotbot', 'mj12bot', 'blexbot',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        /** @var \Config\App $appConfig */
        $appConfig = config('App');
        if (! $appConfig->trackingEnabled) {
            return $response;
        }

        $statusCode = $response->getStatusCode();

        // Skip errors, redirects, and non-page responses
        if ($statusCode >= 400 || $statusCode >= 300) {
            return $response;
        }

        // Skip non-HTML responses (JSON, XML, sitemap, assets)
        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $uri = $request->getUri();
        $path = '/' . ltrim($uri->getPath(), '/');

        // Skip internal paths
        if ($this->isInternalPath($path)) {
            return $response;
        }

        $userAgent = $request instanceof \CodeIgniter\HTTP\IncomingRequest
            ? $request->getUserAgent()->getAgentString()
            : '';

        // Skip bot traffic
        if ($this->isBot($userAgent)) {
            return $response;
        }

        $payload = $this->buildPayload($request, $userAgent, $path, $uri->getQuery());

        try {
            if (! Services::analyticsQueue()->enqueue($payload)) {
                log_message('warning', 'Analytics event could not be written to the local queue.');
            }
        } catch (Throwable $exception) {
            // Analytics must never turn into a public page failure.
            log_message('warning', 'Could not enqueue analytics event: {message}', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $response;
    }

    /**
     * @return array<string, string|null>
     */
    private function buildPayload(RequestInterface $request, string $userAgent, string $path, string $query): array
    {
        $url = $path . ($query !== '' ? '?' . $query : '');

        $referrer = $request->getServer('HTTP_REFERER');
        $referrer = is_string($referrer) && $referrer !== '' ? substr($referrer, 0, 500) : null;

        $parsed = $this->parseUserAgent($userAgent);
        $utms   = $this->extractUtms($query);

        return [
            'url'          => substr($url, 0, 500),
            'referrer'     => $referrer,
            'session_id'   => $this->resolveVisitorId(),
            'device_type'  => $parsed['device_type'],
            'browser'      => $parsed['browser'],
            'os'           => $parsed['os'],
            'utm_source'   => $utms['utm_source'],
            'utm_medium'   => $utms['utm_medium'],
            'utm_campaign' => $utms['utm_campaign'],
        ];
    }

    /**
     * Returns a persistent anonymous visitor UUID stored in a cookie.
     * No personal data — just a random identifier for unique-visitor estimation.
     */
    private function resolveVisitorId(): string
    {
        $cookieName = '_vis';

        if (isset($_COOKIE[$cookieName]) && is_string($_COOKIE[$cookieName]) && strlen($_COOKIE[$cookieName]) === 36) {
            return $_COOKIE[$cookieName];
        }

        $uuid = $this->generateUuid();

        setcookie(
            $cookieName,
            $uuid,
            [
                'expires'  => time() + 31536000, // 1 year
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        return $uuid;
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @return array{device_type: string, browser: string|null, os: string|null}
     */
    private function parseUserAgent(string $ua): array
    {
        $ua = strtolower($ua);

        // Device type
        $isMobile = (bool) preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $ua);
        $isTablet = (bool) preg_match('/ipad|tablet|kindle|silk|playbook/i', $ua);

        if ($isTablet) {
            $deviceType = 'tablet';
        } elseif ($isMobile) {
            $deviceType = 'mobile';
        } else {
            $deviceType = 'desktop';
        }

        // Browser (order matters: Edge before Chrome, Samsung before Android)
        $browser = null;
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera/')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'samsungbrowser/')) {
            $browser = 'Samsung Browser';
        } elseif (str_contains($ua, 'brave/') || str_contains($ua, 'brave chrome/')) {
            $browser = 'Brave';
        } elseif (str_contains($ua, 'firefox/') || str_contains($ua, 'fxios/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'chrome/') || str_contains($ua, 'crios/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'safari/') && str_contains($ua, 'version/')) {
            $browser = 'Safari';
        }

        // OS
        $os = null;
        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'ipad') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macos')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'linux') || str_contains($ua, 'ubuntu')) {
            $os = 'Linux';
        }

        return ['device_type' => $deviceType, 'browser' => $browser, 'os' => $os];
    }

    /**
     * @return array{utm_source: string|null, utm_medium: string|null, utm_campaign: string|null}
     */
    private function extractUtms(string $query): array
    {
        $params = [];
        parse_str($query, $params);

        $get = static function (string $key) use ($params): ?string {
            $val = $params[$key] ?? null;

            return is_string($val) && $val !== '' ? substr($val, 0, 100) : null;
        };

        return [
            'utm_source'   => $get('utm_source'),
            'utm_medium'   => $get('utm_medium'),
            'utm_campaign' => $get('utm_campaign'),
        ];
    }

    private function isBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }

        $ua = strtolower($userAgent);
        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isInternalPath(string $path): bool
    {
        $skip = ['/health', '/ping', '/ready', '/live', '/sitemap', '/api/', '/diagnostics/', '/assets/'];
        foreach ($skip as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

}
