<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Collapses concurrent cache-miss requests for the same key into one
 * upstream fetch, using a blocking `flock()` instead of the "try once, else
 * skip" pattern `App\PageDelivery\FileRegenerationLock` uses for page
 * snapshot regeneration — that pattern intentionally lets losers serve stale
 * content immediately; this one is for `WebApiClient::get()`, which has no
 * stale copy to fall back to on a cold cache, so losers must actually wait
 * for the winner's result instead of firing their own redundant upstream
 * request. See
 * docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.E.
 *
 * `flock()` on a fixed-path file (not `fopen(..., 'x')`) is deliberate: the
 * OS releases the lock automatically when the holding process ends for any
 * reason (normal release, crash, `max_execution_time` kill), so there is no
 * "orphaned lock" state to detect or recover from — unlike a create-exclusive
 * lock file, which needs its own staleness/`filemtime()` check. This also
 * means the lock file itself is never deleted; it is a permanent, reusable
 * lock target, not a signal of "someone is working."
 *
 * Only coordinates workers on the same host/filesystem (`flock()` is not a
 * distributed lock) — the same scope `cache.handler=file` already assumes
 * for this app's cache and the scope its cache-stampede risk actually
 * exists at.
 */
final class SingleFlightLock
{
    public function __construct(
        private readonly string $directory,
        private readonly float $waitTimeoutSeconds = 3.0,
        private readonly int $pollIntervalMicroseconds = 20_000,
    ) {
    }

    /**
     * Runs $onMiss() with single-flight protection for $key. The first
     * caller to arrive acquires the lock and runs $onMiss(); concurrent
     * callers for the same key block (bounded by $waitTimeoutSeconds)
     * until it releases, then call the cheap $onCacheRecheck() to pick up
     * whatever the winner produced — only falling through to their own
     * (expensive) $onMiss() if that recheck comes back empty, either
     * because the wait timed out or the winner's result wasn't cacheable.
     *
     * @template T
     * @param callable(): (T|null) $onCacheRecheck Cheap: cache read only, no origin I/O.
     * @param callable(): T $onMiss Expensive: the actual origin fetch.
     * @return T
     */
    public function single(string $key, callable $onCacheRecheck, callable $onMiss): mixed
    {
        $handle = $this->openLockFile($key);
        if ($handle === null) {
            // No usable lock directory (missing, not writable, disk full) —
            // degrade to uncoordinated fetches rather than fail the request.
            return $onMiss();
        }

        try {
            if (! $this->acquireExclusive($handle)) {
                // Held past our full wait budget: don't make this request
                // wait indefinitely for another one to finish. A duplicate
                // upstream fetch here is the accepted trade-off against
                // ever blocking a request past $waitTimeoutSeconds.
                return $onMiss();
            }

            $existing = $onCacheRecheck();

            return $existing ?? $onMiss();
        } finally {
            // Redundant with the OS releasing the lock on fclose(), but
            // explicit release keeps the timing independent of when PHP
            // actually runs the handle's destructor.
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource|null */
    private function openLockFile(string $key)
    {
        if ($this->directory === '') {
            return null;
        }

        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            return null;
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.lock';
        // 'c': create if missing, never truncate — the file's contents are
        // never read or written, it exists purely as a flock() target.
        $handle = @fopen($path, 'c');

        return $handle === false ? null : $handle;
    }

    /** @param resource $handle */
    private function acquireExclusive($handle): bool
    {
        $deadline = microtime(true) + $this->waitTimeoutSeconds;

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return true;
            }

            usleep($this->pollIntervalMicroseconds);
        } while (microtime(true) < $deadline);

        return false;
    }
}
