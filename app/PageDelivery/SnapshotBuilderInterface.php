<?php

declare(strict_types=1);

namespace App\PageDelivery;

interface SnapshotBuilderInterface
{
    public function build(PageDeliveryRequest $request, bool $force = false): SnapshotBuildResult;
}
