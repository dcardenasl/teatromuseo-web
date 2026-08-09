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
    // v7 invalidates page payloads cached before semantic domain navigation
    // keys were serialized for localized collection CTAs and entry cards.
    // v8 invalidates payloads cached before the canonical public slugs and
    // TeatroEscuela nomenclature were applied to CMS bootstrap data.
    // v9 invalidates entry payloads cached before schema-declared listing
    // fields (including TeatroEscuela start_date) were exposed. v11 also
    // invalidates responses that may contain the removed frontend file URL
    // fallback.
    private const CACHE_SCHEMA_VERSION = 11;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $staleTtl;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 5,
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
        $startedAt = hrtime(true);
        $url       = $this->buildUrl($path, $query);
        $keySuffix = $scope . '_' . md5($url . '|' . $this->currentLocale());
        $cacheKey  = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
        $staleKey  = 'web_api_stale_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
        $cache     = \Config\Services::cache();

        $cached = $cache->get($cacheKey);
        if (is_array($cached)) {
            $result = $this->resultFromArray($cached);
            $this->recordTelemetry($this->telemetryEvent(
                'GET',
                $path,
                $url,
                $scope,
                $result['status'],
                'hit',
                false,
                false,
                $this->elapsedMilliseconds($startedAt),
            ));

            return $result;
        }

        $result = $this->request('GET', $path, $query);
        $timeout = $this->isTimeoutResult($result);

        if ($result['ok']) {
            if ($cacheTtl > 0) {
                $cache->save($cacheKey, $result, $cacheTtl);

                if ($this->staleTtl > 0) {
                    $cache->save($staleKey, $result, $this->staleTtl);
                }
            }

            $this->recordTelemetry($this->telemetryEvent(
                'GET',
                $path,
                $url,
                $scope,
                $result['status'],
                $cacheTtl > 0 ? 'miss' : 'bypass',
                false,
                $timeout,
                $this->elapsedMilliseconds($startedAt),
            ));

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

                $this->recordTelemetry($this->telemetryEvent(
                    'GET',
                    $path,
                    $url,
                    $scope,
                    $result['status'],
                    'stale',
                    true,
                    $timeout,
                    $this->elapsedMilliseconds($startedAt),
                ));

                return $staleResult;
            }
        }

        $this->recordTelemetry($this->telemetryEvent(
            'GET',
            $path,
            $url,
            $scope,
            $result['status'],
            $cacheTtl > 0 ? 'miss' : 'bypass',
            false,
            $timeout,
            $this->elapsedMilliseconds($startedAt),
        ));

        return $result;
    }

    /**
     * Batch GET requests executed in parallel when missing from cache.
     *
     * @param list<array{path: string, query?: array<string, mixed>, cacheTtl?: int, scope?: string}> $requests
     *
     * @return list<array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}>
     */
    public function multiGet(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $batchStartedAt = hrtime(true);
        $results = [];
        $cache   = \Config\Services::cache();

        // First pass: check cache for all requests
        foreach ($requests as $index => $req) {
            $path     = $req['path'] ?? '';
            $query    = is_array($req['query'] ?? null) ? $req['query'] : [];
            $cacheTtl = (int) ($req['cacheTtl'] ?? 300);
            $scope    = (string) ($req['scope'] ?? 'general');

            $url       = $this->buildUrl($path, $query);
            $keySuffix = $scope . '_' . md5($url . '|' . $this->currentLocale());
            $cacheKey  = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;

            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                $results[$index] = $this->resultFromArray($cached);
                $this->recordTelemetry($this->telemetryEvent(
                    'GET',
                    $path,
                    $url,
                    $scope,
                    $results[$index]['status'],
                    'hit',
                    false,
                    false,
                    $this->elapsedMilliseconds($batchStartedAt),
                ));
            }
        }

        // If all are cached, return early
        $misses = array_diff_key(array_flip(array_keys($requests)), $results);
        if ($misses === []) {
            ksort($results);
            return array_values($results);
        }

        // Second pass: fetch missing requests in parallel
        $mh = curl_multi_init();
        $handles = [];
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Language: ' . $this->currentLocale(),
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'X-App-Key: ' . $this->apiKey;
        }

        foreach (array_keys($misses) as $idx) {
            $req   = $requests[$idx];
            $path  = $req['path'] ?? '';
            $query = is_array($req['query'] ?? null) ? $req['query'] : [];
            $url   = $this->buildUrl($path, $query);

            if (trim($url) === '') {
                continue;
            }

            $ch = curl_init($url);
            if ($ch instanceof \CurlHandle) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $this->timeout,
                    CURLOPT_HTTPHEADER     => $headers,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$idx] = $ch;
            }
        }

        // Execute parallel requests
        if ($handles !== []) {
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running > 0) {
                    curl_multi_select($mh, 0.05);
                }
            } while ($running > 0);

            foreach ($handles as $idx => $ch) {
                $req      = $requests[$idx];
                $raw      = curl_multi_getcontent($ch);
                $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                $response = [
                    'raw'       => is_string($raw) ? $raw : false,
                    'status'    => $status,
                    'error'     => $error,
                    'timed_out' => curl_errno($ch) === CURLE_OPERATION_TIMEDOUT,
                ];
                $duration = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
                $result   = $this->parseResponse($response);
                $timeout  = $this->isTimeoutResult($result, $response);

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                // Cache successful results
                if ($result['ok']) {
                    $cacheTtl = (int) ($req['cacheTtl'] ?? 300);
                    $scope    = (string) ($req['scope'] ?? 'general');
                    if ($cacheTtl > 0) {
                        $path      = $req['path'] ?? '';
                        $query     = is_array($req['query'] ?? null) ? $req['query'] : [];
                        $url       = $this->buildUrl($path, $query);
                        $keySuffix = $scope . '_' . md5($url . '|' . $this->currentLocale());
                        $cacheKey  = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
                        $cache->save($cacheKey, $result, $cacheTtl);

                        if ($this->staleTtl > 0) {
                            $staleKey = 'web_api_stale_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
                            $cache->save($staleKey, $result, $this->staleTtl);
                        }
                    }
                } elseif ($result['status'] === 0 || $result['status'] >= 500) {
                    // Keep the same stale-on-outage contract as get(). This is
                    // especially important for block prefetch, which uses
                    // multiGet() for all block data.
                    $path      = $req['path'] ?? '';
                    $query     = is_array($req['query'] ?? null) ? $req['query'] : [];
                    $scope     = (string) ($req['scope'] ?? 'general');
                    $url       = $this->buildUrl($path, $query);
                    $keySuffix = $scope . '_' . md5($url . '|' . $this->currentLocale());
                    $staleKey  = 'web_api_stale_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
                    $stale     = $cache->get($staleKey);

                    if (is_array($stale)) {
                        $staleResult                  = $this->resultFromArray($stale);
                        $staleResult['meta']['stale'] = true;
                        $result                        = $staleResult;
                    }
                }

                $path      = $req['path'] ?? '';
                $query     = is_array($req['query'] ?? null) ? $req['query'] : [];
                $scope     = (string) ($req['scope'] ?? 'general');
                $url       = $this->buildUrl($path, $query);
                $cacheTtl  = (int) ($req['cacheTtl'] ?? 300);
                $isStale   = (bool) ($result['meta']['stale'] ?? false);
                $cacheMode = $isStale ? 'stale' : ($cacheTtl > 0 ? 'miss' : 'bypass');
                if ($duration <= 0) {
                    $duration = $this->elapsedMilliseconds($batchStartedAt);
                }

                $this->recordTelemetry($this->telemetryEvent(
                    'GET',
                    $path,
                    $url,
                    $scope,
                    $status,
                    $cacheMode,
                    $isStale,
                    $timeout,
                    $duration,
                ));

                $results[$idx] = $result;
            }
        }

        curl_multi_close($mh);
        ksort($results);
        return array_values($results);
    }

    /**
     * Execute one concurrent batch across clients with different base URLs.
     * Each request keeps the cache, API key, timeout and telemetry policy of its
     * owning WebApiClient instance.
     *
     * @param list<array{client: self, path: string, query?: array<string, mixed>, cacheTtl?: int, scope?: string}> $requests
     * @return list<array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}>
     */
    public static function multiGetAcross(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $startedAt = hrtime(true);
        $cache = \Config\Services::cache();
        $results = [];
        $misses = [];

        foreach ($requests as $index => $request) {
            $client = $request['client'] ?? null;
            if (! $client instanceof self) {
                $results[$index] = [
                    'ok' => false,
                    'status' => 503,
                    'data' => null,
                    'meta' => [],
                    'messages' => ['Invalid cross-domain prefetch client.'],
                ];
                continue;
            }

            $path = (string) ($request['path'] ?? '');
            $query = is_array($request['query'] ?? null) ? $request['query'] : [];
            $scope = (string) ($request['scope'] ?? 'general');
            $url = $client->buildUrl($path, $query);
            $keySuffix = $scope . '_' . md5($url . '|' . $client->currentLocale());
            $cacheKey = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
            $cached = $cache->get($cacheKey);

            if (is_array($cached)) {
                $results[$index] = $client->resultFromArray($cached);
                $client->recordTelemetry($client->telemetryEvent(
                    'GET',
                    $path,
                    $url,
                    $scope,
                    $results[$index]['status'],
                    'hit',
                    false,
                    false,
                    $client->elapsedMilliseconds($startedAt),
                ));
                continue;
            }

            $misses[$index] = [
                'request' => $request,
                'client' => $client,
                'url' => $url,
                'startedAt' => hrtime(true),
            ];
        }

        if ($misses === []) {
            ksort($results);
            return array_values($results);
        }

        $multiHandle = curl_multi_init();
        $handles = [];
        foreach ($misses as $index => $miss) {
            /** @var self $client */
            $client = $miss['client'];
            $request = $miss['request'];
            $handle = curl_init($miss['url']);
            if (! $handle instanceof \CurlHandle) {
                $results[$index] = [
                    'ok' => false,
                    'status' => 0,
                    'data' => null,
                    'meta' => [],
                    'messages' => ['Could not initialize cURL.'],
                ];
                continue;
            }

            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'Accept-Language: ' . $client->currentLocale(),
            ];
            if ($client->apiKey !== '') {
                $headers[] = 'X-App-Key: ' . $client->apiKey;
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $client->timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[$index] = $handle;
            unset($request);
        }

        if ($handles !== []) {
            $running = null;
            do {
                curl_multi_exec($multiHandle, $running);
                if ($running > 0) {
                    curl_multi_select($multiHandle, 0.05);
                }
            } while ($running > 0);
        }

        foreach ($handles as $index => $handle) {
            /** @var self $client */
            $client = $misses[$index]['client'];
            $request = $misses[$index]['request'];
            $path = (string) ($request['path'] ?? '');
            $query = is_array($request['query'] ?? null) ? $request['query'] : [];
            $scope = (string) ($request['scope'] ?? 'general');
            $cacheTtl = (int) ($request['cacheTtl'] ?? 300);
            $raw = curl_multi_getcontent($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            $transportResponse = [
                'raw' => is_string($raw) ? $raw : false,
                'status' => $status,
                'error' => $error,
                'timed_out' => curl_errno($handle) === CURLE_OPERATION_TIMEDOUT,
            ];
            $result = $client->parseResponse($transportResponse);
            $timeout = $client->isTimeoutResult($result, $transportResponse);
            $url = $client->buildUrl($path, $query);

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);

            $keySuffix = $scope . '_' . md5($url . '|' . $client->currentLocale());
            $cacheKey = 'web_api_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
            $staleKey = 'web_api_stale_v' . self::CACHE_SCHEMA_VERSION . '_' . $keySuffix;
            if ($result['ok']) {
                if ($cacheTtl > 0) {
                    $cache->save($cacheKey, $result, $cacheTtl);
                    if ($client->staleTtl > 0) {
                        $cache->save($staleKey, $result, $client->staleTtl);
                    }
                }
            } elseif ($result['status'] === 0 || $result['status'] >= 500) {
                $stale = $cache->get($staleKey);
                if (is_array($stale)) {
                    $staleResult = $client->resultFromArray($stale);
                    $staleResult['meta']['stale'] = true;
                    $result = $staleResult;
                }
            }

            $isStale = (bool) ($result['meta']['stale'] ?? false);
            $cacheMode = $isStale ? 'stale' : ($cacheTtl > 0 ? 'miss' : 'bypass');
            $client->recordTelemetry($client->telemetryEvent(
                'GET',
                $path,
                $url,
                $scope,
                $status,
                $cacheMode,
                $isStale,
                $timeout,
                $client->elapsedMilliseconds((int) $misses[$index]['startedAt']),
            ));
            $results[$index] = $result;
        }

        curl_multi_close($multiHandle);
        ksort($results);

        return array_values($results);
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
        $startedAt = hrtime(true);
        $url       = $this->buildUrl($path);
        $result    = $this->request('POST', $path, [], $data);

        $this->recordTelemetry($this->telemetryEvent(
            'POST',
            $path,
            $url,
            'none',
            $result['status'],
            'bypass',
            false,
            $this->isTimeoutResult($result),
            $this->elapsedMilliseconds($startedAt),
        ));

        return $result;
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

        return $this->parseResponse($response);
    }

    /**
     * Transport layer (cURL). Kept as a protected seam so tests can substitute
     * it with a fake without touching the request/caching logic above.
     *
     * @param non-empty-string   $method
     * @param list<string>       $headers
     *
     * @return array{raw: string|false, status: int, error: string, timed_out?: bool}
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
        $timedOut = curl_errno($ch) === CURLE_OPERATION_TIMEDOUT;
        curl_close($ch);

        return [
            'raw'       => is_string($raw) ? $raw : false,
            'status'    => $status,
            'error'     => $error,
            'timed_out' => $timedOut,
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
    /**
     * Parse raw transport response into normalized result envelope.
     *
     * @param array{raw: string|false, status: int, error: string, timed_out?: bool} $response
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    /**
     * @param array<string, mixed> $response
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    private function parseResponse(array $response): array
    {
        if ($response['raw'] === false) {
            $message = ($response['timed_out'] ?? false) === true
                ? 'cURL timeout'
                : 'cURL error: ' . $response['error'];

            return $this->errorResult(0, $message);
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

    /**
     * Emit one structured event for every remote request or cache lookup.
     * Override this seam in tests or an APM adapter; production writes JSON to
     * the application log so beta can aggregate it by scope and endpoint.
     *
     * @param array<string, mixed> $event
     */
    protected function recordTelemetry(array $event): void
    {
        log_message(
            'info',
            '[web-api] ' . (string) json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function telemetryEvent(
        string $method,
        string $path,
        string $url,
        string $scope,
        int $status,
        string $cacheState,
        bool $stale,
        bool $timeout,
        float $durationMs,
    ): array {
        return [
            'component'        => 'teatromuseo-web',
            'event'            => 'web_api_request',
            'method'           => $method,
            'request_path'     => $this->currentRequestPath(),
            'path'             => $path,
            'remote_endpoint'  => (string) (parse_url($url, PHP_URL_PATH) ?? $url),
            'scope'            => $scope,
            'duration_ms'      => round($durationMs, 2),
            'status'           => $status,
            'cache_state'      => $cacheState,
            'cache_hit'        => $cacheState === 'hit',
            'stale'            => $stale,
            'timeout'          => $timeout,
        ];
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }

    /**
     * @param array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>} $result
     * @param array{raw?: string|false, status?: int, error?: string, timed_out?: bool} $response
     */
    private function isTimeoutResult(array $result, array $response = []): bool
    {
        if (($response['timed_out'] ?? false) === true) {
            return true;
        }

        $message = strtolower(implode(' ', $result['messages']));

        return $result['status'] === 0
            && (str_contains($message, 'timed out') || str_contains($message, 'timeout'));
    }

    private function currentRequestPath(): string
    {
        try {
            return trim((string) service('request')->getUri()->getPath(), '/');
        } catch (\Throwable) {
            return '';
        }
    }
}
