<?php

declare(strict_types=1);

namespace App\PageDelivery;

interface SnapshotStoreInterface
{
    public function read(string $key): ?PageSnapshot;
}
