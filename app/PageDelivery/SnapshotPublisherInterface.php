<?php

declare(strict_types=1);

namespace App\PageDelivery;

/**
 * Authoritative shared snapshot backend.
 *
 * The read seam remains deliberately small, while publishing and invalidation
 * are explicit capabilities that can be disabled independently in local or
 * single-worker environments.
 */
interface SnapshotPublisherInterface extends SnapshotStoreInterface
{
    public function publish(PageSnapshot $snapshot): bool;

    /**
     * @param list<string> $scopes
     * @param list<string> $locales
     * @param list<string> $routes
     */
    public function invalidateScopes(array $scopes, array $locales = [], array $routes = []): int;

    /** @return array<string, mixed> */
    public function status(): array;
}
