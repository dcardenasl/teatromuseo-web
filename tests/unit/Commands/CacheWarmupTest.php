<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\CacheWarmup;
use App\Libraries\WebApiClientInterface;
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

    public function testApiWarmupChangesAndRestoresCliLocaleContext(): void
    {
        $config = config('App');
        $previousPageDeliveryEnabled = $config->pageDeliveryEnabled;
        $previousPageDeliveryMode = $config->pageDeliveryMode;
        $previousBffRoutes = $config->pageDeliveryBffRoutes;
        $previousLanguageLocale = service('language')->getLocale();
        $previousIntlLocale = \Locale::getDefault();
        $client = new RecordingWarmupBffClient();

        Services::injectMock('request', new CLIRequest($config));
        Services::injectMock('bffWebApiClient', $client);
        $config->pageDeliveryEnabled = false;
        $config->pageDeliveryMode = 'snapshot';
        $config->pageDeliveryBffRoutes = ['home'];

        try {
            (new CacheWarmup(service('logger'), service('commands')))->run(['locale' => 'es', 'route' => 'home']);

            $this->assertSame('public-read/es/page-resolve/home', $client->calls[0]['path'] ?? null);
            $this->assertSame(['es'], $client->requestLocales);
            $this->assertSame(['es'], $client->languageLocales);
            $this->assertSame($previousLanguageLocale, service('language')->getLocale());
            $this->assertSame($previousIntlLocale, \Locale::getDefault());
        } finally {
            $config->pageDeliveryEnabled = $previousPageDeliveryEnabled;
            $config->pageDeliveryMode = $previousPageDeliveryMode;
            $config->pageDeliveryBffRoutes = $previousBffRoutes;
            service('language')->setLocale($previousLanguageLocale);
            \Locale::setDefault($previousIntlLocale);
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

    public function multiGet(array $requests): array
    {
        unset($requests);

        return [];
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
