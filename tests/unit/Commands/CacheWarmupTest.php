<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\CacheWarmup;
use App\Libraries\WebApiClientInterface;
use App\PageDelivery\SnapshotPublisherInterface;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/** @internal */
final class CacheWarmupTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testSynchronousWarmupChangesAndRestoresCliLocaleContext(): void
    {
        $config = config('App');
        $previousPageDeliveryMode = $config->pageDeliveryMode;
        $previousLanguageLocale = service('language')->getLocale();
        $previousIntlLocale = \Locale::getDefault();
        $client = new RecordingWarmupBffClient();

        Services::injectMock('request', new CLIRequest($config));
        Services::injectMock('bffWebApiClient', $client);
        $config->pageDeliveryMode = 'sync';

        try {
            (new CacheWarmup(service('logger'), service('commands')))->run(['locale' => 'es', 'route' => 'home']);

            $this->assertSame('public-read/es/page-resolve/home', $client->calls[0]['path'] ?? null);
            $this->assertSame(['es'], $client->requestLocales);
            $this->assertSame(['es'], $client->languageLocales);
            $this->assertSame($previousLanguageLocale, service('language')->getLocale());
            $this->assertSame($previousIntlLocale, \Locale::getDefault());
        } finally {
            $config->pageDeliveryMode = $previousPageDeliveryMode;
            service('language')->setLocale($previousLanguageLocale);
            \Locale::setDefault($previousIntlLocale);
        }
    }

    public function testStrictWarmupFailsClosedWhenSnapshotBackendIsDisabled(): void
    {
        $config = config('App');
        $previousPageDeliveryMode = $config->pageDeliveryMode;
        $client = new RecordingWarmupBffClient();
        $store = $this->createMock(SnapshotPublisherInterface::class);
        $store->expects($this->once())
            ->method('status')
            ->willReturn(['enabled' => false, 'backend' => 'null', 'shared' => false]);

        Services::injectMock('request', new CLIRequest($config));
        Services::injectMock('bffWebApiClient', $client);
        Services::injectMock('pageSnapshotStore', $store);
        $config->pageDeliveryMode = 'snapshot';

        try {
            $result = (new CacheWarmup(service('logger'), service('commands')))->run([
                'locale' => 'es',
                'route' => 'home',
                'strict' => true,
            ]);

            self::assertSame(2, $result);
            self::assertSame([], $client->calls);
        } finally {
            $config->pageDeliveryMode = $previousPageDeliveryMode;
        }
    }
}

final class RecordingWarmupBffClient implements WebApiClientInterface
{
    /** @var list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> */
    public array $calls = [];

    /** @var list<string> */
    public array $requestLocales = [];

    /** @var list<string> */
    public array $languageLocales = [];

    public function get(string $path, array $query = [], int $cacheTtl = 300, string $scope = 'general'): array
    {
        $this->requestLocales[] = service('request')->getLocale();
        $this->languageLocales[] = service('language')->getLocale();
        $this->calls[] = [
            'path' => $path,
            'query' => $query,
            'cacheTtl' => $cacheTtl,
            'scope' => $scope,
        ];

        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'outcome' => 'page',
                'page' => ['title' => 'Warm-up fixture'],
                'layout' => [],
                'block_context' => [],
                'meta' => ['locale' => 'es', 'route' => 'home'],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
                'messages' => [],
            ],
            'meta' => [],
            'messages' => [],
        ];
    }

    public function post(string $path, array $data = []): array
    {
        unset($path, $data);

        return [
            'ok' => false,
            'status' => 405,
            'data' => null,
            'meta' => [],
            'messages' => ['Not supported by this test double.'],
        ];
    }
}
