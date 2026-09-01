<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\Libraries\WebApiClientInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\SynchronousPageDeliveryAdapter;
use PHPUnit\Framework\TestCase;

final class SynchronousPageDeliveryAdapterTest extends TestCase
{
    public function testPageEnvelopeIsMappedToThePageDeliveryContract(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())->method('get')
            ->with('public-read/es/page-resolve/home', [], 300, 'page-resolve')
            ->willReturn($this->bffResponse([
                'outcome' => 'page',
                'page' => ['page_type' => 'cms_page', 'title' => 'Inicio'],
                'layout' => ['settings' => ['site_name' => 'Teatro Museo']],
                'block_context' => ['block_prefetch_complete' => true],
                'meta' => ['locale' => 'es', 'route' => 'home'],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            ]));

        $response = (new SynchronousPageDeliveryAdapter($client))->deliver(PageDeliveryRequest::home('es'));

        self::assertTrue($response->isAvailable());
        self::assertSame('cms_page', $response->page['page_type']);
        self::assertSame(['site_name' => 'Teatro Museo'], $response->layout['settings']);
        self::assertSame('home', $response->meta['route']);
    }

    public function testPreviewAndQueryVariantsAreForwarded(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())->method('get')->with(
            'public-read/es/page-resolve/home',
            [
                'q' => 'libro',
                'preview' => '1',
                'preview_expires' => '2026-08-14T12:00:00Z',
                'preview_sig' => 'signature',
            ],
            300,
            'page-resolve',
        )->willReturn($this->bffResponse([
            'outcome' => 'page',
            'page' => ['page_type' => 'cms_page'],
            'layout' => [],
            'block_context' => [],
            'meta' => [],
            'source' => [],
        ]));

        $request = PageDeliveryRequest::home(
            locale: 'es',
            preview: true,
            previewExpires: '2026-08-14T12:00:00Z',
            previewSignature: 'signature',
            query: ['q' => 'libro'],
        );

        self::assertTrue((new SynchronousPageDeliveryAdapter($client))->deliver($request)->isAvailable());
    }

    public function testRedirectAndNotFoundOutcomesRemainNonPageResponses(): void
    {
        $redirectClient = $this->createMock(WebApiClientInterface::class);
        $redirectClient->method('get')->willReturn($this->bffResponse([
            'outcome' => 'redirect',
            'redirect' => ['path' => '/inicio', 'status' => 301],
            'meta' => [],
        ]));
        $redirect = (new SynchronousPageDeliveryAdapter($redirectClient))->deliver(PageDeliveryRequest::home('es'));
        self::assertTrue($redirect->isRedirect());
        self::assertSame(301, $redirect->status);

        $notFoundClient = $this->createMock(WebApiClientInterface::class);
        $notFoundClient->method('get')->willReturn($this->bffResponse([
            'outcome' => 'not_found',
            'messages' => ['Public page was not found.'],
            'meta' => [],
        ]));
        $notFound = (new SynchronousPageDeliveryAdapter($notFoundClient))->deliver(PageDeliveryRequest::home('es'));
        self::assertFalse($notFound->isAvailable());
        self::assertSame(404, $notFound->status);
    }

    public function testStaleClientMetadataIsPromotedToThePageSource(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => [
                'outcome' => 'page',
                'page' => ['page_type' => 'cms_page'],
                'layout' => [],
                'block_context' => [],
                'meta' => [],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
            ],
            'meta' => ['stale' => true],
            'messages' => [],
        ]);

        $response = (new SynchronousPageDeliveryAdapter($client))->deliver(PageDeliveryRequest::home('es'));

        self::assertSame('stale', $response->source['state']);
        self::assertTrue($response->source['stale']);
    }

    /** @param array<string, mixed> $data */
    private function bffResponse(array $data): array
    {
        return [
            'ok' => ($data['outcome'] ?? '') === 'page',
            'status' => ($data['outcome'] ?? '') === 'not_found' ? 404 : 200,
            'data' => $data,
            'meta' => [],
            'messages' => [],
        ];
    }
}
