<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\PageDeliveryRequest;
use PHPUnit\Framework\TestCase;

final class PageDeliveryRequestTest extends TestCase
{
    public function testCacheIdentityIncludesOnlySupportedVariantParameters(): void
    {
        $first = PageDeliveryRequest::home('ES', query: [
            'page' => 2,
            'q' => 'mask',
            'unused' => 'must-not-change-public-identity',
        ]);
        $second = PageDeliveryRequest::home('es', query: [
            'q' => 'mask',
            'page' => '2',
        ]);

        $this->assertSame('es', $first->locale);
        $this->assertSame(['page' => '2', 'q' => 'mask'], $first->query);
        $this->assertSame($first->cacheKey(), $second->cacheKey());
    }

    public function testPreviewQueryIsExplicitAndNeverEmptyForNormalDelivery(): void
    {
        $normal = PageDeliveryRequest::home('es');
        $preview = PageDeliveryRequest::home('es', true, '123', 'signature');

        $this->assertSame([], $normal->previewQuery());
        $this->assertSame([
            'preview' => '1',
            'preview_expires' => '123',
            'preview_sig' => 'signature',
        ], $preview->previewQuery());
    }
}
