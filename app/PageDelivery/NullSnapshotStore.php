<?php

declare(strict_types=1);

namespace App\PageDelivery;

/** Explicitly disabled until a shared snapshot backend is configured. */
final class NullSnapshotStore implements SnapshotPublisherInterface
{
    public function read(string $key): ?PageSnapshot
    {
        unset($key);

        return null;
    }

    public function publish(PageSnapshot $snapshot): bool
    {
        unset($snapshot);

        return false;
    }

    /** @param list<string> $scopes @param list<string> $locales @param list<string> $routes */
    public function invalidateScopes(array $scopes, array $locales = [], array $routes = []): int
    {
        unset($scopes, $locales, $routes);

        return 0;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            'enabled' => false,
            'backend' => 'null',
            'shared' => false,
        ];
    }
}
