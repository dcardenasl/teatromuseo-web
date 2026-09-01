<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\PageDeliveryResponse;
use PHPUnit\Framework\TestCase;

final class PageDeliveryResponseTest extends TestCase
{
    public function testFailureEnvelopeDeclaresUnavailableSourceState(): void
    {
        $response = PageDeliveryResponse::failure(503, ['Snapshot unavailable.']);

        self::assertSame(503, $response->status);
        self::assertFalse($response->isAvailable());
        self::assertSame([
            'version' => 1,
            'ok' => false,
            'data' => null,
            'meta' => ['version' => 1],
            'source' => [
                'domain' => 'web',
                'state' => 'unavailable',
                'stale' => false,
            ],
            'messages' => ['Snapshot unavailable.'],
        ], $response->envelope());
    }

    public function testRedirectCarriesNoPageAndIsNeverAvailable(): void
    {
        $response = PageDeliveryResponse::redirect('/es/cartelera', 301, ['route' => 'about']);

        self::assertSame(301, $response->status);
        self::assertNull($response->page);
        self::assertFalse($response->isAvailable());
        self::assertTrue($response->isRedirect());
        self::assertSame('/es/cartelera', $response->meta['redirect_to']);
        self::assertSame('about', $response->meta['route']);
    }

    public function testFailureIsNeverMistakenForARedirect(): void
    {
        $notFound = PageDeliveryResponse::failure(404, ['Public page was not found.']);

        self::assertFalse($notFound->isRedirect());
    }
}
