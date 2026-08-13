<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-request context for correlation and public delivery telemetry.
 *
 * PHP-FPM handles one request per worker at a time, so a small static holder is
 * safe here. The web filter resets it at the beginning of every request; unit
 * tests can call reset() between assertions.
 */
final class RequestContext
{
    private static ?string $requestId = null;
    private static ?float $startedAt = null;

    /** @var list<array<string, mixed>> */
    private static array $outbound = [];

    /** @var array<string, int> */
    private static array $phaseStartedAt = [];

    /** @var array<string, float> */
    private static array $phaseDurations = [];

    /** @var array<string, mixed> */
    private static array $pageDelivery = [];

    public static function begin(string $requestId): void
    {
        self::$requestId = $requestId;
        self::$startedAt = microtime(true);
        self::$outbound  = [];
        self::$phaseStartedAt = [];
        self::$phaseDurations = [];
        self::$pageDelivery = [];
    }

    public static function reset(): void
    {
        self::$requestId = null;
        self::$startedAt = null;
        self::$outbound  = [];
        self::$phaseStartedAt = [];
        self::$phaseDurations = [];
        self::$pageDelivery = [];
    }

    public static function requestId(): ?string
    {
        return self::$requestId;
    }

    public static function elapsedMilliseconds(): float
    {
        return self::$startedAt === null ? 0.0 : (microtime(true) - self::$startedAt) * 1000;
    }

    /** @param array<string, mixed> $event */
    public static function recordOutbound(array $event): void
    {
        self::$outbound[] = $event;
    }

    /**
     * Start a named page-render phase. Repeated starts for the same phase are
     * ignored until the active measurement is stopped, which prevents nested
     * controller helpers from corrupting the timing.
     */
    public static function startPhase(string $phase): void
    {
        if (self::$startedAt === null || $phase === '' || isset(self::$phaseStartedAt[$phase])) {
            return;
        }

        self::$phaseStartedAt[$phase] = hrtime(true);
    }

    /** Stop a named phase and add its duration to the request aggregate. */
    public static function stopPhase(string $phase): void
    {
        if (! isset(self::$phaseStartedAt[$phase])) {
            return;
        }

        $startedAt = self::$phaseStartedAt[$phase];
        unset(self::$phaseStartedAt[$phase]);
        self::$phaseDurations[$phase] = (self::$phaseDurations[$phase] ?? 0.0)
            + ((hrtime(true) - $startedAt) / 1_000_000);
    }

    /** Add a measured fallback segment to an existing phase aggregate. */
    public static function addPhaseDuration(string $phase, float $durationMs): void
    {
        if (self::$startedAt === null || $phase === '' || $durationMs < 0) {
            return;
        }

        self::$phaseDurations[$phase] = (self::$phaseDurations[$phase] ?? 0.0) + $durationMs;
    }

    /**
     * Measure a callable without changing its return value or exception
     * behavior. This is used at the composition seam so PageDelivery and the
     * legacy controller path report the same phase.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function measurePhase(string $phase, callable $callback): mixed
    {
        if (self::$startedAt === null) {
            return $callback();
        }

        self::startPhase($phase);

        try {
            return $callback();
        } finally {
            self::stopPhase($phase);
        }
    }

    /** Close any phase left open by an exception or an early response. */
    public static function finishOpenPhases(): void
    {
        foreach (array_keys(self::$phaseStartedAt) as $phase) {
            self::stopPhase($phase);
        }
    }

    /** @param array<string, mixed> $metadata */
    public static function setPageDelivery(array $metadata): void
    {
        if (self::$startedAt !== null) {
            self::$pageDelivery = $metadata;
        }
    }

    /**
     * @return array{route_resolution_ms: ?float, composition_ms: ?float, view_render_ms: ?float, unattributed_ms: ?float, delivery: array<string, mixed>}
     */
    public static function pageRenderSummary(): ?array
    {
        // A route can end in a redirect without rendering a page. Emit this
        // event only for a rendered response or a PageDelivery outcome, while
        // still allowing an error response to report its route timing when it
        // reached the renderer.
        $hasPagePhase = self::$pageDelivery !== []
            || array_key_exists('page_composition', self::$phaseDurations)
            || array_key_exists('view_render', self::$phaseDurations);

        if (! $hasPagePhase) {
            return null;
        }

        $routeResolution = self::phaseDuration('route_resolution');
        $composition = self::phaseDuration('page_composition');
        $viewRender = self::phaseDuration('view_render');
        $measured = array_sum(array_filter([$routeResolution, $composition, $viewRender], static fn (?float $value): bool => $value !== null));

        return [
            'route_resolution_ms' => $routeResolution,
            'composition_ms' => $composition,
            'view_render_ms' => $viewRender,
            // Keeps the phase breakdown honest when controller-side data
            // shaping or framework overhead sits between measured seams.
            'unattributed_ms' => round(max(0.0, self::elapsedMilliseconds() - $measured), 2),
            'delivery' => self::$pageDelivery,
        ];
    }

    private static function phaseDuration(string $phase): ?float
    {
        return array_key_exists($phase, self::$phaseDurations)
            ? round(self::$phaseDurations[$phase], 2)
            : null;
    }

    /**
     * @return array{count:int,duration_ms:float,payload_bytes:int,cache_hits:int,stale:int,timeouts:int,source_revisions:list<string>,snapshot_revisions:list<string>}
     */
    public static function outboundSummary(): array
    {
        $duration = 0.0;
        $bytes = 0;
        $cacheHits = 0;
        $stale = 0;
        $timeouts = 0;
        $sourceRevisions = [];
        $snapshotRevisions = [];

        foreach (self::$outbound as $event) {
            $duration += (float) ($event['duration_ms'] ?? 0);
            $bytes += max(0, (int) ($event['payload_bytes'] ?? 0));
            $cacheHits += ($event['cache_hit'] ?? false) === true ? 1 : 0;
            $stale += ($event['stale'] ?? false) === true ? 1 : 0;
            $timeouts += ($event['timeout'] ?? false) === true ? 1 : 0;

            $sourceRevision = $event['source_revision'] ?? null;
            if (is_string($sourceRevision) && $sourceRevision !== '') {
                $sourceRevisions[$sourceRevision] = true;
            }
            $snapshotRevision = $event['snapshot_revision'] ?? null;
            if (is_string($snapshotRevision) && $snapshotRevision !== '') {
                $snapshotRevisions[$snapshotRevision] = true;
            }
        }

        return [
            'count'              => count(self::$outbound),
            'duration_ms'        => round($duration, 2),
            'payload_bytes'      => $bytes,
            'cache_hits'         => $cacheHits,
            'stale'              => $stale,
            'timeouts'           => $timeouts,
            'source_revisions'   => array_keys($sourceRevisions),
            'snapshot_revisions' => array_keys($snapshotRevisions),
        ];
    }
}
