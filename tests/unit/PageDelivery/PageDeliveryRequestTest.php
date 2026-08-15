<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\PageDeliveryRequest;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testBoundedVariantsRemainSnapshotEligible(): void
    {
        $request = PageDeliveryRequest::home('es', query: [
            'category' => 'ceramics',
            'tag' => 'colonial',
            'page' => '2',
            'per_page' => '24',
            'order_by' => 'name',
            'order_direction' => 'asc',
            'limit' => '12',
            'filter_by' => 'category',
            'filter_operator' => 'eq',
        ]);

        $this->assertTrue($request->isSnapshotEligible());
    }

    public function testRoutePolicyCanDisableSnapshotsWithoutChangingTheRequestIdentity(): void
    {
        $request = new PageDeliveryRequest(
            locale: 'es',
            route: 'noticias/entrada',
            snapshotEligible: false,
        );

        $this->assertFalse($request->isSnapshotEligible());
        $this->assertFalse($request->useBff);
        $this->assertSame('es', $request->locale);
        $this->assertSame('noticias/entrada', $request->route);
    }

    /** @return iterable<string, array{string}> */
    public static function unboundedVariantKeys(): iterable
    {
        yield 'free-text search q' => ['q'];
        yield 'free-text search alias' => ['search'];
        yield 'free-text filter value' => ['filter_value'];
    }

    #[DataProvider('unboundedVariantKeys')]
    public function testFreeTextVariantsDisqualifyTheRequestFromSnapshotEligibility(string $key): void
    {
        $request = PageDeliveryRequest::home('es', query: [$key => 'anything a visitor might type']);

        $this->assertFalse($request->isSnapshotEligible());
    }

    public function testFreeTextVariantsStillParticipateInTheCacheIdentity(): void
    {
        // Ineligible for the snapshot store does not mean "ignored" — a
        // synchronous render still needs the exact query to compose the
        // right result, and two different search terms must never collide.
        $first = PageDeliveryRequest::home('es', query: ['q' => 'one']);
        $second = PageDeliveryRequest::home('es', query: ['q' => 'two']);

        $this->assertNotSame($first->cacheKey(), $second->cacheKey());
    }
}
