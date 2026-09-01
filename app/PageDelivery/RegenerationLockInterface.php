<?php

declare(strict_types=1);

namespace App\PageDelivery;

interface RegenerationLockInterface
{
    public function acquire(string $key): ?string;

    public function release(string $key, string $token): void;
}
