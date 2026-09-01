<?php

declare(strict_types=1);

namespace App\PageDelivery;

interface PageDeliveryInterface
{
    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse;
}
