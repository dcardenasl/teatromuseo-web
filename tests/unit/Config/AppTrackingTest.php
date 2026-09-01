<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Tests\Support\Libraries\ConfigReader;

final class AppTrackingTest extends TestCase
{
    public function testTrackingIsDisabledOnlyForTheTestRuntimeByDefault(): void
    {
        $config = new ConfigReader();

        $this->assertFalse($config->trackingEnabled);
    }
}
