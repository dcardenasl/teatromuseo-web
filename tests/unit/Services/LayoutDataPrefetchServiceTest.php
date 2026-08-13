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

    public function testPrefetchesLayoutInOneCallWithoutOverwritingProvidedMenus(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('public-read/es/layout', [], 600, 'layout')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    'navigation' => [
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
                    'collections' => [],
                    'settings' => ['site_name' => 'TeatroMuseo'],
                ],
                'meta' => [],
                'messages' => [],
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

    public function testResolvesCollectionSlugFromTargetIdUsingTheBundledCollectionsList(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('public-read/es/layout', [], 600, 'layout')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    'navigation' => [
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
                        'footer' => null,
                        'legal' => null,
                    ],
                    'collections' => [
                        ['id' => 3, 'slug' => 'festivales', 'localized_slugs' => ['es' => 'festivales']],
                    ],
                    'settings' => [],
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
            ->method('get')
            ->with('public-read/en/layout', [], 600, 'layout')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    'navigation' => ['main' => ['items' => []], 'footer' => null, 'legal' => null],
                    'collections' => [],
                    'settings' => ['site_name' => 'TeatroMuseo'],
                ],
                'meta' => [],
                'messages' => [],
            ]);

        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData(['socialLinks' => []], 'en');

        $this->assertSame(['site_name' => 'TeatroMuseo'], $result['settings']);
    }

    public function testNothingMissingSkipsTheRequestEntirely(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->never())->method('get');
        $client->expects($this->never())->method('multiGet');

        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData([
            'mainMenu' => ['items' => []],
            'footerMenu' => ['items' => []],
            'legalMenu' => ['items' => []],
            'settings' => [],
            'socialLinks' => [],
        ]);

        $this->assertSame([], $result);
    }

    public function testFailedRequestFallsBackToEmptyMenusAndSettings(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok' => false,
            'status' => 503,
            'data' => null,
            'meta' => [],
            'messages' => ['unavailable'],
        ]);

        $result = (new LayoutDataPrefetchService($client))->prefetchLayoutData(['socialLinks' => []]);

        $this->assertSame(['items' => []], $result['mainMenu']);
        $this->assertSame(['items' => []], $result['footerMenu']);
        $this->assertSame(['items' => []], $result['legalMenu']);
        $this->assertSame([], $result['settings']);
    }
}
