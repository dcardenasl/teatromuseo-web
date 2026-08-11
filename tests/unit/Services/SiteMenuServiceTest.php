<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SiteMenuService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/** @internal */
final class SiteMenuServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
        service('request')->setLocale('es');
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    public function testReadsConsolidatedNavigationAndNormalizesSemanticUrls(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('public-read/es/navigation', [], 600, 'menus')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    'main' => [
                        'items' => [
                            [
                                'label' => 'Contacto',
                                'navigation' => ['route_key' => 'pages', 'slug' => 'contacto'],
                                'children' => [],
                            ],
                            [
                                'label' => 'Festivales',
                                'navigation' => [
                                    'route_key' => 'catalog',
                                    'target_type' => 'collection_listing',
                                    'collection_slug' => 'festivales',
                                ],
                                'children' => [],
                            ],
                            [
                                'label' => 'Sitio externo',
                                'url' => 'https://example.com/teatro',
                                'navigation' => [],
                                'children' => [],
                            ],
                        ],
                    ],
                    'footer' => null,
                    'legal' => null,
                ],
                'meta' => [],
                'messages' => [],
            ]);

        $menu = (new SiteMenuService($client))->getMenu('header');

        $this->assertSame('/contacto', $menu['items'][0]['custom_url']);
        $this->assertSame('/festivales', $menu['items'][1]['custom_url']);
        $this->assertSame('https://example.com/teatro', $menu['items'][2]['custom_url']);
    }

    public function testEmptyNavigationLocationReturnsAnEmptyMenu(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => ['main' => null, 'footer' => null, 'legal' => null],
            'meta' => [],
            'messages' => [],
        ]);

        $this->assertSame(['items' => []], (new SiteMenuService($client))->getMenu('main'));
    }

    public function testResolvesCollectionSlugFromTargetIdWhenCmsOmitsIt(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        'main' => [
                            'items' => [[
                                'label' => 'Festivales',
                                'navigation' => [
                                    'route_key' => 'catalog',
                                    'target_type' => 'collection_listing',
                                    'target_id' => 3,
                                ],
                                'children' => [],
                            ]],
                        ],
                    ],
                    'meta' => [],
                    'messages' => [],
                ],
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        ['id' => 3, 'slug' => 'festivales', 'localized_slugs' => ['es' => 'festivales']],
                    ],
                    'meta' => [],
                    'messages' => [],
                ],
            );

        $menu = (new SiteMenuService($client))->getMenu('main');

        $this->assertSame('/festivales', $menu['items'][0]['custom_url']);
    }
}
