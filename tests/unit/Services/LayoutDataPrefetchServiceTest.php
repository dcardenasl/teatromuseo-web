<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\LayoutDataPrefetchService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/** @internal */
final class LayoutDataPrefetchServiceTest extends CIUnitTestCase
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

    public function testPrefetchesLocalizedNavigationWithoutOverwritingProvidedMenus(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return $requests === [
                    ['path' => 'public-read/es/navigation', 'cacheTtl' => 600, 'scope' => 'menus'],
                    ['path' => 'public-read/es/settings', 'cacheTtl' => 3600, 'scope' => 'settings'],
                ];
            }))
            ->willReturn([
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        'main' => [
                            'items' => [[
                                'label' => 'Eventos nuevos',
                                'navigation' => ['route_key' => 'events'],
                                'children' => [],
                            ]],
                        ],
                        'footer' => [
                            'name' => 'Footer',
                            'items' => [[
                                'label' => 'Festivales',
                                'navigation' => [
                                    'route_key' => 'catalog',
                                    'target_type' => 'collection_listing',
                                    'collection_slug' => 'festivales',
                                ],
                                'children' => [],
                            ]],
                        ],
                        'legal' => null,
                    ],
                    'meta' => [],
                    'messages' => [],
                ],
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => ['site_name' => 'TeatroMuseo'],
                    'meta' => [],
                    'messages' => [],
                ],
            ]);

        $providedMain = ['items' => [['label' => 'Menú entregado por el controlador']]];
        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData([
            'mainMenu' => $providedMain,
            'socialLinks' => [],
        ]);

        $this->assertArrayNotHasKey('mainMenu', $result);
        $this->assertSame('/festivales', $result['footerMenu']['items'][0]['custom_url']);
        $this->assertSame(['items' => []], $result['legalMenu']);
        $this->assertSame(['site_name' => 'TeatroMuseo'], $result['settings']);
    }

    public function testResolvesCollectionSlugFromTargetIdWhenCmsOmitsIt(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with([
                ['path' => 'public-read/es/navigation', 'cacheTtl' => 600, 'scope' => 'menus'],
            ])
            ->willReturn([[
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
            ]]);
        $client->expects($this->once())
            ->method('get')
            ->with('public/es/collections', [], 3600, 'collections')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    ['id' => 3, 'slug' => 'festivales', 'localized_slugs' => ['es' => 'festivales']],
                ],
                'meta' => [],
                'messages' => [],
            ]);

        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData([
            'settings' => [],
            'socialLinks' => [],
        ]);

        $this->assertSame('/festivales', $result['mainMenu']['items'][0]['custom_url']);
    }

    public function testExplicitLocaleIsUsedOutsideAnHttpRequest(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with([
                ['path' => 'public-read/en/navigation', 'cacheTtl' => 600, 'scope' => 'menus'],
                ['path' => 'public-read/en/settings', 'cacheTtl' => 3600, 'scope' => 'settings'],
            ])
            ->willReturn([
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => ['main' => ['items' => []]],
                    'meta' => [],
                    'messages' => [],
                ],
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => ['site_name' => 'TeatroMuseo'],
                    'meta' => [],
                    'messages' => [],
                ],
            ]);

        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData(['socialLinks' => []], 'en');

        $this->assertSame(['site_name' => 'TeatroMuseo'], $result['settings']);
    }
}
