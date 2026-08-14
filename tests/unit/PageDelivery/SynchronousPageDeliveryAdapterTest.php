<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\Libraries\WebApiClientInterface;
use App\PageDelivery\ClockInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\SynchronousPageDeliveryAdapter;
use App\Services\BlockPrefetchService;
use App\Services\LayoutDataPrefetchService;
use App\Services\SitePageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SynchronousPageDeliveryAdapterTest extends TestCase
{
    public function testPageEnvelopeIsMappedToThePageDeliveryContract(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with('public-read/es/page-resolve/home', [], 300, 'page-resolve')
            ->willReturn($this->bffResult([
                'outcome' => 'page',
                'redirect' => null,
                'page' => ['page_type' => 'cms_page', 'title' => 'Inicio'],
                'layout' => ['settings' => ['site_name' => 'Teatro Museo']],
                'block_context' => ['block_prefetch' => [], 'block_prefetch_complete' => true],
                'meta' => ['locale' => 'es', 'route' => 'home'],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
                'messages' => [],
            ]));

        $response = $this->adapter($client)->deliver(PageDeliveryRequest::home('es'));

        self::assertTrue($response->isAvailable());
        self::assertSame(200, $response->status);
        self::assertSame('cms_page', $response->page['page_type']);
        self::assertSame(['site_name' => 'Teatro Museo'], $response->layout['settings']);
        self::assertSame('bff', $response->source['domain']);
        self::assertSame('home', $response->meta['route']);
    }

    public function testHomepagePreviewForwardsVariantsAndPreviewSignature(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with(
                'public-read/es/page-resolve/home',
                [
                    'q' => 'libro',
                    'preview' => '1',
                    'preview_expires' => '2026-08-14T12:00:00Z',
                    'preview_sig' => 'signature',
                ],
                300,
                'page-resolve',
            )
            ->willReturn($this->bffResult([
                'outcome' => 'page',
                'redirect' => null,
                'page' => ['page_type' => 'collection_entry', 'slug' => 'mi pagina'],
                'layout' => [],
                'block_context' => [],
                'meta' => ['locale' => 'es', 'route' => 'home'],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
                'messages' => [],
            ]));

        $request = PageDeliveryRequest::home(
            locale: 'es',
            preview: true,
            previewExpires: '2026-08-14T12:00:00Z',
            previewSignature: 'signature',
            query: ['q' => 'libro'],
        );

        $response = $this->adapter($client)->deliver($request);

        self::assertTrue($response->isAvailable());
        self::assertSame('collection_entry', $response->page['page_type']);
    }

    public function testConfiguredNonHomepageBffRouteUsesTheFullPageResolver(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with('public-read/es/page-resolve/contacto', [], 300, 'page-resolve')
            ->willReturn($this->bffResult([
                'outcome' => 'page',
                'redirect' => null,
                'page' => ['page_type' => 'cms_page', 'title' => 'Contacto'],
                'layout' => [],
                'block_context' => [],
                'meta' => ['locale' => 'es', 'route' => 'contacto'],
                'source' => ['domain' => 'bff', 'state' => 'fresh', 'stale' => false],
                'messages' => [],
            ]));

        $response = (new SynchronousPageDeliveryAdapter(
            $this->createStub(SitePageService::class),
            $this->createStub(LayoutDataPrefetchService::class),
            new BlockPrefetchService($client),
            new AdapterClock(),
            bff: $client,
            bffRoutes: ['contacto'],
        ))->deliver(new PageDeliveryRequest('es', 'contacto'));

        self::assertTrue($response->isAvailable());
        self::assertSame('cms_page', $response->page['page_type']);
        self::assertSame('contacto', $response->meta['route']);
    }

    public function testHomepageRedirectAndNotFoundOutcomesRemainNonPageResponses(): void
    {
        $redirectClient = $this->createMock(WebApiClientInterface::class);
        $redirectClient->expects(self::once())
            ->method('get')
            ->willReturn($this->bffResult([
                'outcome' => 'redirect',
                'redirect' => ['path' => '/inicio', 'status' => 301],
                'page' => null,
                'layout' => [],
                'block_context' => [],
                'meta' => ['locale' => 'es', 'route' => 'home'],
                'source' => ['domain' => 'bff', 'state' => 'unavailable', 'stale' => false],
                'messages' => [],
            ]));

        $redirect = $this->adapter($redirectClient)->deliver(PageDeliveryRequest::home('es'));
        self::assertTrue($redirect->isRedirect());
        self::assertSame(301, $redirect->status);
        self::assertSame('/inicio', $redirect->meta['redirect_to']);

        $notFoundClient = $this->createMock(WebApiClientInterface::class);
        $notFoundClient->expects(self::once())
            ->method('get')
            ->willReturn($this->bffResult([
                'outcome' => 'not_found',
                'redirect' => null,
                'page' => null,
                'layout' => [],
                'block_context' => [],
                'meta' => ['locale' => 'es', 'route' => 'missing'],
                'source' => ['domain' => 'bff', 'state' => 'unavailable', 'stale' => false],
                'messages' => ['Public page was not found.'],
            ]));

        $notFound = $this->adapter($notFoundClient)->deliver(PageDeliveryRequest::home('es'));
        self::assertFalse($notFound->isAvailable());
        self::assertSame(404, $notFound->status);
        self::assertSame(['Public page was not found.'], $notFound->messages);
    }

    public function testStaleBffClientMetadataIsPromotedToThePageSource(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn([
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

        $response = $this->adapter($client)->deliver(PageDeliveryRequest::home('es'));

        self::assertSame('stale', $response->source['state']);
        self::assertTrue($response->source['stale']);
    }

    private function adapter(WebApiClientInterface $client): SynchronousPageDeliveryAdapter
    {
        return new SynchronousPageDeliveryAdapter(
            $this->createStub(SitePageService::class),
            $this->createStub(LayoutDataPrefetchService::class),
            new BlockPrefetchService($client),
            new AdapterClock(),
            bff: $client,
        );
    }

    /** @param array<string, mixed> $data */
    private function bffResult(array $data): array
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

final class AdapterClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-14T12:00:00+00:00');
    }
}
