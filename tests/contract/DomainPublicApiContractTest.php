<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Libraries\WebApiClient;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Services;

final class DomainPublicApiContractTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('cache', new MockCache());
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testVersionedPublicReadEnvelopeIsConsumableByWebClient(): void
    {
        $client = new ContractWebApiClient((string) json_encode([
            'version' => 1,
            'ok' => true,
            'data' => ['id' => 7, 'title' => 'Public event'],
            'meta' => [
                'locale' => 'es',
                'source_revision' => 'events:revision-1',
                'fields' => ['id', 'title'],
            ],
            'source' => ['domain' => 'events', 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ], JSON_THROW_ON_ERROR));

        $result = $client->get('public-read/es/events/7', [], 0, 'events');

        $this->assertTrue($result['ok']);
        $this->assertSame(['id' => 7, 'title' => 'Public event'], $result['data']);
        $this->assertSame('events:revision-1', $result['meta']['source_revision'] ?? null);
        $this->assertSame([], $result['messages']);
    }

    public function testAllPublicReadProvidersExposeTheVersionedEnvelope(): void
    {
        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            $this->markTestSkipped(
                'Run composer test:contract:integration with the three isolated domain endpoints.',
            );
        }

        $sharedAppKey = $this->environment('DOMAIN_CONTRACT_APP_KEY');
        $contracts = [
            'cms' => [
                'base_url' => 'DOMAIN_CONTRACT_CMS_BASE_URL',
                'path' => '/api/v1/public-read/es/pages',
            ],
            'catalog' => [
                'base_url' => 'DOMAIN_CONTRACT_CATALOG_BASE_URL',
                'path' => '/api/v1/public-read/es/collection-items',
            ],
            'events' => [
                'base_url' => 'DOMAIN_CONTRACT_EVENTS_BASE_URL',
                'path' => '/api/v1/public-read/es/events',
            ],
        ];

        foreach ($contracts as $domain => $contract) {
            $baseUrl = $this->environment($contract['base_url']);
            $specificAppKey = getenv('DOMAIN_CONTRACT_' . strtoupper($domain) . '_APP_KEY');
            $appKey = is_string($specificAppKey) && trim($specificAppKey) !== ''
                ? trim($specificAppKey)
                : $sharedAppKey;

            $this->assertNotSame('', $appKey, $domain . ' contract app key is required.');

            [$unauthenticatedStatus] = $this->request($baseUrl, $contract['path']);
            $this->assertSame(401, $unauthenticatedStatus, $domain . ' must reject missing X-App-Key.');

            [$status, $contentType, $body] = $this->request($baseUrl, $contract['path'], $appKey);
            $this->assertSame(200, $status, $domain . ' PublicRead listing must be available.');
            $this->assertStringContainsString('application/json', $contentType);

            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($payload);
            $this->assertPublicReadEnvelope($payload, $domain);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertPublicReadEnvelope(array $payload, string $domain): void
    {
        $this->assertTrue($payload['ok'] ?? false, $domain . ' envelope must be successful.');
        $this->assertSame(1, $payload['version'] ?? null);
        $this->assertIsArray($payload['data'] ?? null);
        $this->assertIsArray($payload['meta'] ?? null);
        $this->assertIsArray($payload['source'] ?? null);
        $this->assertSame($domain, $payload['source']['domain'] ?? null);
        $this->assertSame('fresh', $payload['source']['state'] ?? null);
        $this->assertFalse($payload['source']['stale'] ?? true);
        $this->assertIsArray($payload['messages'] ?? null);
    }

    private function environment(string $name): string
    {
        $value = getenv($name);
        $value = is_string($value) ? trim($value) : '';
        $this->assertNotSame('', $value, $name . ' is required.');

        return rtrim($value, '/');
    }

    /** @return array{int, string, string} */
    private function request(string $baseUrl, string $path, ?string $appKey = null): array
    {
        $headers = ['Accept: application/json'];
        if ($appKey !== null) {
            $headers[] = 'X-App-Key: ' . $appKey;
        }

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = @file_get_contents($baseUrl . $path, false, $context);

        $headers = $http_response_header ?? [];
        preg_match('#\s(\d{3})\s#', $headers[0] ?? '', $statusMatch);
        $status = (int) ($statusMatch[1] ?? 0);
        $this->assertNotSame(0, $status, 'Domain contract endpoint is unreachable: ' . $baseUrl . $path);
        $contentType = '';
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), 'content-type:')) {
                $contentType = trim(substr($header, strlen('content-type:')));
                break;
            }
        }

        return [$status, $contentType, is_string($body) ? $body : ''];
    }
}

/** @internal */
final class ContractWebApiClient extends WebApiClient
{
    public function __construct(private readonly string $payload)
    {
        parent::__construct('http://contract.test', 'test-web-api-key', 1, 0);
    }

    /** @return array{raw: string, status: int, error: string} */
    protected function execute(string $method, string $url, array $headers, ?string $jsonBody): array
    {
        unset($method, $url, $headers, $jsonBody);

        return ['raw' => $this->payload, 'status' => 200, 'error' => ''];
    }
}
