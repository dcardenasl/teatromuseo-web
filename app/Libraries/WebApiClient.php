<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Support\RequestContext;

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
    // fallback. v12 switches full-page delivery to the BFF page-resolve
    // envelope and must not reuse any pre-cutover response shape.
    private const CACHE_SCHEMA_VERSION = 12;

    /**
     * Above this size, a response is worth a human looking at — either a
     * listing block requesting more than it needs (see
     * docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.E)
     * or a genuinely large detail payload. Deliberately just a log-level
     * signal, not a hard cap: once §2.A-§2.C bounded the worst-case payload
     * structurally, a size cap here would only ever fire on a regression —
     * and should be *investigated*, not silently truncated.
     */
    private const PAYLOAD_WARNING_BYTES = 200_000;

    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $connectTimeout;
    private int $staleTtl;
    private int $lastPayloadBytes = 0;
    private SingleFlightLock $singleFlightLock;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 5,
        int $staleTtl = 86400,
        int $connectTimeout = 1,
        ?SingleFlightLock $singleFlightLock = null,
    ) {
        if (trim($baseUrl) === '') {
            throw new \LogicException(
                'Missing public-read API base URL. Set BFF_API_BASE_URL in .env. '
                . 'Example: BFF_API_BASE_URL=http://localhost:8188'
            );
        }

        if (trim($apiKey) === '') {
            throw new \LogicException(
                'Missing BFF_API_KEY. Set it in .env. '
                . 'This should be the key registered for the BFF public-read surface.'
            );
        }

        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->apiKey   = $apiKey;
        $this->timeout  = max(1, $timeout);
        $this->staleTtl = max(0, $staleTtl);
        $this->connectTimeout = min($this->timeout, max(1, $connectTimeout));
        $this->singleFlightLock = $singleFlightLock ?? new SingleFlightLock(defined('WRITABLEPATH') ? WRITABLEPATH . 'cache/locks' : '');
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
                $this->payloadBytes($result),
                $this->sourceRevision($result),
                $this->snapshotRevision($result),
            ));

            return $result;
        }

        if ($cacheTtl <= 0) {
            $result = $this->request('GET', $path, $query);
            $timeout = $this->isTimeoutResult($result);
            $this->recordTelemetry($this->telemetryEvent(
                'GET',
                $path,
                $url,
                $scope,
                $result['status'],
                'bypass',
                false,
                $timeout,
                $this->elapsedMilliseconds($startedAt),
                $this->lastOrResultPayloadBytes($result),
                $this->sourceRevision($result),
                $this->snapshotRevision($result),
            ));

            return $result;
        }

        $missExecuted = false;

        $result = $this->singleFlightLock->single(
            $cacheKey,
            function () use ($cache, $cacheKey) {
                $cached = $cache->get($cacheKey);
                return is_array($cached) ? $this->resultFromArray($cached) : null;
            },
            function () use ($path, $query, $cache, $cacheKey, $staleKey, $cacheTtl, &$missExecuted) {
                $missExecuted = true;
                $res = $this->request('GET', $path, $query);
                if ($res['ok']) {
                    $cache->save($cacheKey, $res, $cacheTtl);

                    if ($this->staleTtl > 0) {
                        $cache->save($staleKey, $res, $this->staleTtl);
                    }
                }
                return $res;
            }
        );

        $timeout = $this->isTimeoutResult($result);

        if ($result['ok']) {
            $this->recordTelemetry($this->telemetryEvent(
                'GET',
                $path,
                $url,
                $scope,
                $result['status'],
                $missExecuted ? 'miss' : 'hit',
                false,
                $timeout,
                $this->elapsedMilliseconds($startedAt),
                $this->lastOrResultPayloadBytes($result),
                $this->sourceRevision($result),
                $this->snapshotRevision($result),
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
                $staleResult['meta']['source'] = $this->staleSource($staleResult['meta']['source'] ?? null);

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
                    $this->lastOrResultPayloadBytes($staleResult),
                    $this->sourceRevision($staleResult),
                    $this->snapshotRevision($staleResult),
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
            $missExecuted ? 'miss' : 'hit',
            false,
            $timeout,
            $this->elapsedMilliseconds($startedAt),
            $this->lastOrResultPayloadBytes($result),
            $this->sourceRevision($result),
            $this->snapshotRevision($result),
        ));

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
            $this->lastOrResultPayloadBytes($result),
            $this->sourceRevision($result),
            $this->snapshotRevision($result),
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

        $requestId = RequestContext::requestId();
        if ($requestId !== null) {
            $headers[] = 'X-Request-ID: ' . $requestId;
        }

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
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_NOSIGNAL       => true,
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
     * Parse raw transport response into normalized result envelope.
     *
     * @param array{raw: string|false, status: int, error: string, timed_out?: bool} $response
     *
     * @return array{ok: bool, status: int, data: mixed, meta: array<string, mixed>, messages: list<string>}
     */
    private function parseResponse(array $response): array
    {
        $this->lastPayloadBytes = is_string($response['raw'] ?? null)
            ? strlen($response['raw'])
            : 0;

        if ($response['raw'] === false) {
            $message = ($response['timed_out'] ?? false) === true
                ? 'cURL timeout'
                : 'cURL error: ' . $response['error'];

            return $this->errorResult(0, $message);
        }

        $decoded  = json_decode($response['raw'], true);
        $data     = is_array($decoded) ? ($decoded['data'] ?? $decoded) : null;
        $meta     = is_array($decoded) && is_array($decoded['meta'] ?? null) ? $this->stringKeyed($decoded['meta']) : [];
        if (is_array($decoded) && is_array($decoded['source'] ?? null)) {
            $meta['source'] = $this->stringKeyed($decoded['source']);
        }
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
     * @param mixed $source
     * @return array<string, mixed>
     */
    protected function staleSource(mixed $source): array
    {
        $normalized = is_array($source) ? $this->stringKeyed($source) : [];
        $normalized['state'] = 'stale';
        $normalized['stale'] = true;

        return $normalized;
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

    /** @param array{data:mixed,meta:array<string,mixed>} $result */
    private function payloadBytes(array $result): int
    {
        $encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? 0 : strlen($encoded);
    }

    /** @param array{data:mixed,meta:array<string,mixed>} $result */
    private function lastOrResultPayloadBytes(array $result): int
    {
        return $this->lastPayloadBytes > 0 ? $this->lastPayloadBytes : $this->payloadBytes($result);
    }

    /** @param array{meta:array<string,mixed>} $result */
    private function sourceRevision(array $result): ?string
    {
        return is_string($result['meta']['source_revision'] ?? null)
            ? $result['meta']['source_revision']
            : null;
    }

    /** @param array{meta:array<string,mixed>} $result */
    private function snapshotRevision(array $result): ?string
    {
        return is_string($result['meta']['snapshot_revision'] ?? null)
            ? $result['meta']['snapshot_revision']
            : null;
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
        $event['request_id'] ??= RequestContext::requestId();
        $event['locale'] ??= $this->currentLocale();
        $event['payload_bytes'] ??= 0;
        $event['source_revision'] ??= null;
        $event['snapshot_revision'] ??= null;
        RequestContext::recordOutbound($event);

        $encoded = (string) json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        log_message('info', '[web-api] ' . $encoded);

        if ((int) $event['payload_bytes'] > self::PAYLOAD_WARNING_BYTES) {
            log_message('warning', '[web-api] oversized payload (' . $event['payload_bytes'] . ' bytes) ' . $encoded);
        }
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
        int $payloadBytes = 0,
        ?string $sourceRevision = null,
        ?string $snapshotRevision = null,
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
            'payload_bytes'    => max(0, $payloadBytes),
            'source_revision'  => $sourceRevision,
            'snapshot_revision' => $snapshotRevision,
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
