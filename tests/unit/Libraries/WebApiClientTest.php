<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\WebApiClient;
use App\Support\RequestContext;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

/** @internal */
final class InstrumentedWebApiClient extends WebApiClient
{
    /** @var list<array{method: string, url: string, headers: list<string>, jsonBody: string|null}> */
    public array $calls = [];

    /** @var list<array<string, mixed>> */
    public array $telemetry = [];

    /**
     * @param list<array{raw: string|false, status: int, error: string}> $responses
     */
    public function __construct(private array $responses)
    {
        parent::__construct('http://domain.test', 'test_app_key', 5, 3600);
    }

    protected function execute(string $method, string $url, array $headers, ?string $jsonBody): array
    {
        $this->calls[] = [
            'method'   => $method,
            'url'      => $url,
            'headers'  => $headers,
            'jsonBody' => $jsonBody,
        ];

        $next = array_shift($this->responses);

        return $next ?? ['raw' => false, 'status' => 0, 'error' => 'no scripted response left'];
    }

    /** @param array<string, mixed> $event */
    protected function recordTelemetry(array $event): void
    {
        $this->telemetry[] = $event;
    }
}

/**
 * Unit tests for WebApiClient using the protected execute() seam: a subclass
 * replaces the cURL transport with scripted responses, so caching, stale
 * fallback and envelope normalization are tested without network access.
 *
 * @internal
 */
final class WebApiClientTest extends CIUnitTestCase
{
    private MockCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new MockCache();
        Services::injectMock('cache', $this->cache);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();
        Services::reset(true);

        parent::tearDown();
    }

    /**
     * @param list<array{raw: string|false, status: int, error: string}> $responses
     */
    private function makeClient(array $responses): InstrumentedWebApiClient
    {
        return new InstrumentedWebApiClient($responses);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{raw: string, status: int, error: string}
     */
    private function jsonResponse(array $payload, int $status = 200): array
    {
        return ['raw' => (string) json_encode($payload), 'status' => $status, 'error' => ''];
    }

    public function testGetReturnsNormalizedEnvelopeOnSuccess(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 7], 'meta' => ['total' => 1]]),
        ]);

        $result = $client->get('public/es/pages/home');

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertSame(['id' => 7], $result['data']);
        $this->assertSame(['total' => 1], $result['meta']);
        $this->assertSame([], $result['messages']);
    }

    public function testGetCachesSuccessfulResponses(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 1]]),
        ]);

        $first  = $client->get('public/es/pages/home', [], 300, 'pages');
        $second = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertSame($first['data'], $second['data']);
        $this->assertCount(1, $client->calls, 'Second call must be served from cache');
    }

    public function testGetEmitsCacheAndEndpointTelemetry(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 1]]),
        ]);

        $client->get('public/es/pages/home', [], 300, 'pages');
        $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertCount(2, $client->telemetry);
        $this->assertSame('miss', $client->telemetry[0]['cache_state']);
        $this->assertFalse($client->telemetry[0]['cache_hit']);
        $this->assertSame('hit', $client->telemetry[1]['cache_state']);
        $this->assertTrue($client->telemetry[1]['cache_hit']);
        $this->assertSame('public/es/pages/home', $client->telemetry[0]['path']);
        $this->assertSame('/api/v1/public/es/pages/home', $client->telemetry[0]['remote_endpoint']);
        $this->assertSame(200, $client->telemetry[0]['status']);
        $this->assertIsFloat($client->telemetry[0]['duration_ms']);
        $this->assertGreaterThan(0, $client->telemetry[0]['payload_bytes']);
    }

    public function testPropagatesRequestIdAndRevisionsInTelemetry(): void
    {
        RequestContext::begin('request-5678');
        $client = $this->makeClient([
            $this->jsonResponse([
                'data' => ['id' => 1],
                'meta' => [
                    'source_revision' => 'cms:7',
                    'snapshot_revision' => 'snapshot:2',
                ],
            ]),
        ]);

        $client->get('public/es/pages/home', [], 0, 'pages');

        $this->assertContains('X-Request-ID: request-5678', $client->calls[0]['headers']);
        $this->assertSame('cms:7', $client->telemetry[0]['source_revision']);
        $this->assertSame('snapshot:2', $client->telemetry[0]['snapshot_revision']);
        $this->assertGreaterThan(0, $client->telemetry[0]['payload_bytes']);
    }

    public function testGetMarksTimeoutsInTelemetry(): void
    {
        $client = $this->makeClient([
            ['raw' => false, 'status' => 0, 'error' => 'Operation timed out after 5000 milliseconds'],
        ]);

        $result = $client->get('public/es/collections', [], 300, 'collections');

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $client->telemetry);
        $this->assertTrue($client->telemetry[0]['timeout']);
        $this->assertSame('miss', $client->telemetry[0]['cache_state']);
        $this->assertSame(0, $client->telemetry[0]['status']);
    }

    public function testGetDoesNotCacheFailures(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['error' => 'boom'], 500),
            $this->jsonResponse(['data' => ['id' => 1]]),
        ]);

        $first  = $client->get('public/es/pages/home', [], 300, 'pages');
        $second = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertFalse($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertCount(2, $client->calls);
    }

    public function testGetServesStaleCopyWhenUpstreamReturns500(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse([
                'data' => ['id' => 42],
                'source' => ['domain' => 'events', 'state' => 'fresh', 'stale' => false],
            ]),
            $this->jsonResponse(['error' => 'upstream down'], 500),
        ]);

        // Prime fresh + stale caches, then expire the fresh copy only.
        $client->get('public/es/pages/home', [], 300, 'pages');
        $this->cache->deleteMatching('web_api_v*');

        $result = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertTrue($result['ok']);
        $this->assertSame(['id' => 42], $result['data']);
        $this->assertTrue($result['meta']['stale'] ?? false, 'Stale responses must be flagged in meta');
        $this->assertSame('stale', $result['meta']['source']['state'] ?? null);
        $this->assertTrue($result['meta']['source']['stale'] ?? false);
    }

    public function testGetServesStaleCopyWhenProviderReturns508(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 508]]),
            $this->jsonResponse(['error' => 'entry process limit'], 508),
        ]);

        $client->get('public/es/pages/home', [], 300, 'pages');
        $this->cache->deleteMatching('web_api_v*');

        $result = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertTrue($result['ok']);
        $this->assertSame(['id' => 508], $result['data']);
        $this->assertTrue($result['meta']['stale'] ?? false);
        $this->assertSame(508, $client->telemetry[1]['status']);
        $this->assertSame('stale', $client->telemetry[1]['cache_state']);
    }

    public function testGetPreservesProviderSourceStateInNormalizedMeta(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse([
                'data' => ['id' => 42],
                'source' => ['domain' => 'catalog', 'state' => 'fresh', 'stale' => false],
            ]),
        ]);

        $result = $client->get('public-read/es/collection-items/42', [], 0, 'collection_items');

        $this->assertSame('catalog', $result['meta']['source']['domain'] ?? null);
        $this->assertSame('fresh', $result['meta']['source']['state'] ?? null);
        $this->assertFalse($result['meta']['source']['stale'] ?? true);
    }

    public function testGetServesStaleCopyOnTransportFailure(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 42]]),
            ['raw' => false, 'status' => 0, 'error' => 'Connection refused'],
        ]);

        $client->get('public/es/pages/home', [], 300, 'pages');
        $this->cache->deleteMatching('web_api_v*');

        $result = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertTrue($result['ok']);
        $this->assertSame(['id' => 42], $result['data']);
        $this->assertTrue($result['meta']['stale'] ?? false);
    }

    public function testGetNeverMasksA404WithStaleData(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 42]]),
            $this->jsonResponse(['message' => 'Not found'], 404),
        ]);

        $client->get('public/es/pages/home', [], 300, 'pages');
        $this->cache->deleteMatching('web_api_v*');

        $result = $client->get('public/es/pages/home', [], 300, 'pages');

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['status']);
        $this->assertArrayNotHasKey('stale', $result['meta']);
    }

    public function testTransportFailureDegradesGracefully(): void
    {
        $client = $this->makeClient([
            ['raw' => false, 'status' => 0, 'error' => 'Could not resolve host'],
        ]);

        $result = $client->get('public/es/pages/home');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['status']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('Could not resolve host', $result['messages'][0]);
    }

    public function testQueryParametersAreAppendedToUrl(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => []]),
        ]);

        $client->get('public/es/entries/news', ['page' => 2, 'per_page' => 12], 0);

        $this->assertStringContainsString(
            '/api/v1/public/es/entries/news?page=2&per_page=12',
            $client->calls[0]['url']
        );
    }

    public function testAppKeyHeaderIsSentOnEveryRequest(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => []]),
        ]);

        $client->get('public/settings', [], 0);

        $this->assertContains('X-App-Key: test_app_key', $client->calls[0]['headers']);
    }

    public function testPostSendsJsonBodyAndIsNeverCached(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 9]], 201),
            $this->jsonResponse(['data' => ['id' => 10]], 201),
        ]);

        $first  = $client->post('public/submissions', ['form_key' => 'contact']);
        $second = $client->post('public/submissions', ['form_key' => 'contact']);

        $this->assertTrue($first['ok']);
        $this->assertSame(['id' => 10], $second['data']);
        $this->assertCount(2, $client->calls);
        $this->assertSame('POST', $client->calls[0]['method']);
        $this->assertSame('{"form_key":"contact"}', $client->calls[0]['jsonBody']);
    }

    public function testExtractsMessagesFromStringAndArrayShapes(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['message' => 'Invalid key', 'errors' => ['a' => 'first', 'b' => 'second']], 422),
        ]);

        $result = $client->get('public/settings', [], 0);

        $this->assertFalse($result['ok']);
        $this->assertSame(['Invalid key', 'first', 'second'], $result['messages']);
    }

    public function testZeroTtlSkipsCaching(): void
    {
        $client = $this->makeClient([
            $this->jsonResponse(['data' => ['id' => 1]]),
            $this->jsonResponse(['data' => ['id' => 2]]),
        ]);

        $client->get('public/settings', [], 0);
        $result = $client->get('public/settings', [], 0);

        $this->assertSame(['id' => 2], $result['data']);
        $this->assertCount(2, $client->calls);
    }
}
