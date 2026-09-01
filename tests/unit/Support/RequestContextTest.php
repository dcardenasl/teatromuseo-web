<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RequestContext;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RequestContextTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testSummarizesOutboundCacheAndRevisionSignals(): void
    {
        RequestContext::begin('request-1234');
        RequestContext::recordOutbound([
            'duration_ms' => 12.345,
            'payload_bytes' => 512,
            'cache_hit' => true,
            'stale' => false,
            'timeout' => false,
            'source_revision' => 'cms:1',
            'snapshot_revision' => 'snapshot:1',
        ]);
        RequestContext::recordOutbound([
            'duration_ms' => 4,
            'payload_bytes' => 128,
            'cache_hit' => false,
            'stale' => true,
            'timeout' => true,
            'source_revision' => 'cms:1',
            'snapshot_revision' => null,
        ]);

        $summary = RequestContext::outboundSummary();

        $this->assertSame(2, $summary['count']);
        $this->assertSame(16.35, $summary['duration_ms']);
        $this->assertSame(640, $summary['payload_bytes']);
        $this->assertSame(1, $summary['cache_hits']);
        $this->assertSame(1, $summary['stale']);
        $this->assertSame(1, $summary['timeouts']);
        $this->assertSame(['cms:1'], $summary['source_revisions']);
        $this->assertSame(['snapshot:1'], $summary['snapshot_revisions']);
    }

    public function testSummarizesPageRenderPhasesAndDeliveryMetadata(): void
    {
        RequestContext::begin('request-page-1234');
        RequestContext::addPhaseDuration('route_resolution', 12.345);
        RequestContext::addPhaseDuration('page_composition', 34.567);
        RequestContext::addPhaseDuration('view_render', 5.678);
        RequestContext::setPageDelivery([
            'available' => true,
            'cache' => 'fresh',
            'state' => 'fresh',
        ]);

        $summary = RequestContext::pageRenderSummary();

        $this->assertNotNull($summary);
        $this->assertSame(12.35, $summary['route_resolution_ms']);
        $this->assertSame(34.57, $summary['composition_ms']);
        $this->assertSame(5.68, $summary['view_render_ms']);
        $this->assertIsFloat($summary['unattributed_ms']);
        $this->assertSame('fresh', $summary['delivery']['cache']);
    }

    public function testDoesNotTreatRedirectOnlyRouteTimingAsPageRender(): void
    {
        RequestContext::begin('request-redirect-1234');
        RequestContext::startPhase('route_resolution');
        RequestContext::stopPhase('route_resolution');

        $this->assertNull(RequestContext::pageRenderSummary());
    }
}
