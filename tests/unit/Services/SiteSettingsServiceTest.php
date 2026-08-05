<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClient;
use App\Services\SiteSettingsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SiteSettingsServiceTest extends CIUnitTestCase
{
    private function makeService(array $getReturn): SiteSettingsService
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient->method('get')->willReturn($getReturn);

        return new SiteSettingsService($apiClient);
    }

    public function testGetAllReturnsPublicSettings(): void
    {
        $service = $this->makeService([
            'ok' => true,
            'data' => [
                'site_name' => 'Teatro Museo',
                'site_description' => 'Sitio público.',
            ],
        ]);

        $settings = $service->getAll();

        $this->assertSame('Teatro Museo', $settings['site_name']);
        $this->assertSame('Sitio público.', $settings['site_description']);
    }

    public function testGetReturnsDefaultWhenApiFailsOrKeyIsMissing(): void
    {
        $service = $this->makeService(['ok' => false, 'data' => null]);

        $this->assertSame('fallback', $service->get('missing_key', 'fallback'));
    }
}
