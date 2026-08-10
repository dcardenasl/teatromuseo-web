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
}
