<?php

declare(strict_types=1);

namespace App\PageDelivery;

final class NullRegenerationLock implements RegenerationLockInterface
{
    public function acquire(string $key): ?string
    {
        unset($key);

        return null;
    }

    public function release(string $key, string $token): void
    {
        unset($key, $token);
    }
}
