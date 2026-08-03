<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * WebApiClient — HTTP client for the public website.
 *
 * Key differences from the admin ApiClient:
 * - No JWT authentication (public site reads only)
 * - Sends X-App-Key header with every request
 * - GET responses are cached in CI4 cache (default 300 s)
 * - Keeps a long-lived "stale" copy of every successful GET and serves it
 *   when the Domain API is unreachable (transport failure) or returns 5xx,
 *   so the public site survives Domain downtime until the stale TTL expires
 * - No file upload capability
 */
class WebApiClient implements WebApiClientInterface
{
    // Bump when the public API payload shape or seeded content changes in a way
    // that should invalidate every cached web page response.
    // v5 invalidates responses produced before the Contacto social-link tree
    // was seeded as part of the canonical CMS page block definition.
    private const CACHE_SCHEMA_VERSION = 5;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $staleTtl;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 15,
        int $staleTtl = 86400
    ) {
        if (trim($baseUrl) === '') {
            throw new \LogicException(
                'Missing WEB_API_BASE_URL. Set it in .env. '
                . 'Example: WEB_API_BASE_URL=http://localhost:8190'
            );
        }

        if (trim($apiKey) === '') {
            throw new \LogicException(
                'Missing WEB_API_KEY. Set it in .env. '
                . 'This should be a registered API key from your domain API.'
            );
        }

        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->apiKey   = $apiKey;
        $this->timeout  = max(1, $timeout);
        $this->staleTtl = max(0, $staleTtl);
    }

    /**
     * GET request with server-side caching and stale fallback.
     *
     * @param array<string, mixed> $query
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    public function get(string $path, array $query = [], int $cacheTtl = 300, string $scope = 'general'): array
    {
        $url       = $this->buildUrl($path, $query);
        $keySuffix = $scope . '_' . md5($url . '|' . $this->currentLocale());
        $cacheKey  = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
        $staleKey  = 'web_api_stale_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
        $cache     = \Config\Services::cache();

        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->resultFromArray($cached);
        }

        $result = $this->request('GET', $path, $query);

        if ($result['ok']) {
            if ($cacheTtl > 0) {
                $cache->save($cacheKey, $result, $cacheTtl);

                if ($this->staleTtl > 0) {
                    $cache->save($staleKey, $result, $this->staleTtl);
                }
            }

            return $result;
        }

        // Stale fallback only on transport failure (status 0) or upstream 5xx.
        // A 4xx (e.g. 404) is a valid answer and must never be masked by stale data.
        if ($result['status'] === 0 || $result['status'] >= 500) {
            $stale = $cache->get($staleKey);
            if (is_array($stale)) {
                log_message(
                    'warning',
                    "[WebApiClient] Serving stale cache for {$url} (status {$result['status']})"
                );

                $staleResult                  = $this->resultFromArray($stale);
                $staleResult['meta']['stale'] = true;

                return $staleResult;
            }
        }

        return $result;
    }

    /**
     * POST request — not cached (used for form submissions).
     *
     * @param array<string, mixed> $data
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, [], $data);
    }

    /**
     * Core request executor: builds URL and headers, delegates the transport
     * to execute() and normalizes the response envelope.
     *
     * @param 'GET'|'POST'         $method
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    private function request(string $method, string $path, array $query = [], array $body = []): array
    {
        $url = $this->buildUrl($path, $query);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Language: ' . $this->currentLocale(),
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'X-App-Key: ' . $this->apiKey;
        }

        $jsonBody = null;
        if ($method === 'POST' && $body !== []) {
            $encoded  = json_encode($body);
            $jsonBody = $encoded === false ? null : $encoded;
        }

        $response = $this->execute($method, $url, $headers, $jsonBody);

        if ($response['raw'] === false) {
            return $this->errorResult(0, 'cURL error: ' . $response['error']);
        }

        $decoded  = json_decode($response['raw'], true);
        $data     = is_array($decoded) ? ($decoded['data'] ?? $decoded) : null;
        $meta     = is_array($decoded) && is_array($decoded['meta'] ?? null) ? $this->stringKeyed($decoded['meta']) : [];
        $messages = $this->extractMessages($decoded);
        $status   = $response['status'];

        return [
            'ok'       => $status >= 200 && $status < 300,
            'status'   => $status,
            'data'     => $data,
            'meta'     => $meta,
            'messages' => $messages,
        ];
    }

    /**
     * Transport layer (cURL). Kept as a protected seam so tests can substitute
     * it with a fake without touching the request/caching logic above.
     *
     * @param non-empty-string   $method
     * @param list<string>       $headers
     *
     * @return array{raw: string|false, status: int, error: string}
     */
    protected function execute(string $method, string $url, array $headers, ?string $jsonBody): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['raw' => false, 'status' => 0, 'error' => 'Could not initialize cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($jsonBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        return [
            'raw'    => is_string($raw) ? $raw : false,
            'status' => $status,
            'error'  => $error,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $base = $this->baseUrl . '/api/v1/' . ltrim($path, '/');

        if ($query !== []) {
            $base .= '?' . http_build_query($query);
        }

        return $base;
    }

    /**
     * @return array{ok: bool, status: int, data: null, meta: array<string, mixed>, messages: list<string>}
     */
    private function errorResult(int $status, string $message): array
    {
        return [
            'ok'       => false,
            'status'   => $status,
            'data'     => null,
            'meta'     => [],
            'messages' => [$message],
        ];
    }

    /**
     * Rebuild the result envelope from a cached payload. Cache backends return
     * loosely-typed arrays, so the shape is re-validated field by field instead
     * of trusting the stored value blindly.
     *
     * @param array<mixed> $cached
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    private function resultFromArray(array $cached): array
    {
        $messages = [];
        if (is_array($cached['messages'] ?? null)) {
            foreach ($cached['messages'] as $message) {
                if (is_string($message)) {
                    $messages[] = $message;
                }
            }
        }

        return [
            'ok'       => (bool) ($cached['ok'] ?? false),
            'status'   => (int) ($cached['status'] ?? 0),
            'data'     => $cached['data'] ?? null,
            'meta'     => is_array($cached['meta'] ?? null) ? $this->stringKeyed($cached['meta']) : [],
            'messages' => $messages,
        ];
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * @param mixed $decoded
     *
     * @return list<string>
     */
    private function extractMessages(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $messages = [];

        foreach (['message', 'error', 'errors'] as $key) {
            if (! isset($decoded[$key])) {
                continue;
            }

            $val = $decoded[$key];
            if (is_string($val)) {
                $messages[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $v) {
                    if (is_string($v)) {
                        $messages[] = $v;
                    }
                }
            }
        }

        return $messages;
    }

    private function currentLocale(): string
    {
        $locale = service('request')->getLocale();

        return $locale !== '' ? $locale : (string) config('App')->defaultLocale;
    }
}
