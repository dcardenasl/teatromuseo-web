<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\PageResolverService;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PageResolverServiceTest extends TestCase
{
    public function testResolvesBothRedirectAndPageFromOneRequest(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with('public-read/es/page-bootstrap/nosotros', [], 300, 'pages')
            ->willReturn([
                'ok' => true,
                'status' => 200,
                'data' => [
                    'redirect' => ['new_url' => '/es/museo/coleccion', 'redirect_type' => 301],
                    'page' => ['id' => 5, 'title' => 'Nosotros'],
                ],
                'meta' => [],
                'messages' => [],
            ]);

        $result = (new PageResolverService($client))->resolveRedirectAndPage('nosotros', 'es', false, null, null);

        $this->assertSame(['new_url' => '/es/museo/coleccion', 'redirect_type' => 301], $result['redirect']);
        $this->assertSame(['id' => 5, 'title' => 'Nosotros'], $result['page']);
    }

    public function testNullRedirectAndNullPageWhenNeitherExist(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => ['redirect' => null, 'page' => null],
            'meta' => [],
            'messages' => [],
        ]);

        $result = (new PageResolverService($client))->resolveRedirectAndPage('never-existed', 'es', false, null, null);

        $this->assertNull($result['redirect']);
        $this->assertNull($result['page']);
    }

    public function testPreviewAddsQueryParamsAndForcesCacheBypass(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('get')
            ->with(
                'public-read/es/page-bootstrap/nosotros',
                ['preview' => '1', 'preview_expires' => '123', 'preview_sig' => 'abc'],
                0,
                'pages',
            )
            ->willReturn(['ok' => true, 'status' => 200, 'data' => ['redirect' => null, 'page' => null], 'meta' => [], 'messages' => []]);

        (new PageResolverService($client))->resolveRedirectAndPage('nosotros', 'es', true, '123', 'abc');
    }

    public function testFailedRequestYieldsNullRedirectAndNullPage(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok' => false,
            'status' => 503,
            'data' => null,
            'meta' => [],
            'messages' => ['unavailable'],
        ]);

        $result = (new PageResolverService($client))->resolveRedirectAndPage('nosotros', 'es', false, null, null);

        $this->assertNull($result['redirect']);
        $this->assertNull($result['page']);
    }
}
