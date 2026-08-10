<?php

declare(strict_types=1);

namespace App\PageDelivery;

/** Explicitly disabled until a shared snapshot backend is configured. */
final class NullSnapshotStore implements SnapshotStoreInterface
{
    public function read(string $key): ?PageSnapshot
    {
        unset($key);

        return null;
    }
}
