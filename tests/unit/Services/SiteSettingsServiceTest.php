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

    public function testGetContactDefaultsReturnsSeededValues(): void
    {
        $service = $this->makeService([
            'ok' => true,
            'data' => [
                'contact_admin_email' => 'contacto@example.com',
                'contact_from_email' => 'no-reply@example.com',
                'contact_site_name' => 'Mi Sitio',
                'contact_autoreply_message' => 'Gracias por escribirnos.',
            ],
        ]);

        $defaults = $service->getContactDefaults();

        $this->assertSame('contacto@example.com', $defaults['contact_admin_email']);
        $this->assertSame('no-reply@example.com', $defaults['contact_from_email']);
        $this->assertSame('Mi Sitio', $defaults['contact_site_name']);
        $this->assertSame('Gracias por escribirnos.', $defaults['contact_autoreply_message']);
    }

    public function testGetContactDefaultsFallsBackToEmptyStringsWhenApiFails(): void
    {
        $service = $this->makeService(['ok' => false, 'data' => null]);

        $defaults = $service->getContactDefaults();

        $this->assertSame('', $defaults['contact_admin_email']);
        $this->assertSame('', $defaults['contact_from_email']);
        $this->assertSame('', $defaults['contact_site_name']);
        $this->assertSame('', $defaults['contact_autoreply_message']);
    }
}
