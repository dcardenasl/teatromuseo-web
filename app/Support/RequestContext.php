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

    public static function begin(string $requestId): void
    {
        self::$requestId = $requestId;
        self::$startedAt = microtime(true);
        self::$outbound  = [];
    }

    public static function reset(): void
    {
        self::$requestId = null;
        self::$startedAt = null;
        self::$outbound  = [];
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
