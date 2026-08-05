<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClient;
use App\Services\SocialLinksService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SocialLinksServiceTest extends CIUnitTestCase
{
    /** @param array<string, mixed> $settings */
    private function makeService(array $settings): SocialLinksService
    {
        $apiClient = $this->createMock(WebApiClient::class);
        $apiClient->method('get')->willReturn([
            'ok'   => true,
            'data' => $settings,
        ]);

        return new SocialLinksService($apiClient);
    }

    public function testActiveLinksExposeOnlyCanonicalNetworksInDisplayOrder(): void
    {
        $service = $this->makeService([
            'social_facebook' => 'https://facebook.com/teatromuseo',
            'social_instagram' => 'https://instagram.com/teatromuseo',
            'social_youtube' => 'https://youtube.com/@teatromuseo',
            'social_twitter' => 'https://x.com/teatromuseo',
            'social_linkedin' => 'https://linkedin.com/company/teatromuseo',
        ]);

        $this->assertSame(
            [
                ['key' => 'social_facebook', 'label' => 'Facebook', 'url' => 'https://facebook.com/teatromuseo'],
                ['key' => 'social_instagram', 'label' => 'Instagram', 'url' => 'https://instagram.com/teatromuseo'],
                ['key' => 'social_youtube', 'label' => 'YouTube', 'url' => 'https://youtube.com/@teatromuseo'],
            ],
            $service->getActiveLinks()
        );
    }

    public function testAllNetworksContainsOnlyCanonicalNetworkDefinitions(): void
    {
        $networks = $this->makeService([])->getAllNetworks();

        $this->assertSame(
            ['social_facebook', 'social_instagram', 'social_youtube'],
            array_column($networks, 'key')
        );
        $this->assertSame([false, false, false], array_column($networks, 'is_active'));
    }
}
