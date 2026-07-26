<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\Libraries\DeterministicDomainAdapter;

/** @internal */
final class DeterministicDomainAdapterTest extends TestCase
{
    public function testAnyConfiguredLocaleResolvesWithCompleteLocalizedSlugSet(): void
    {
        $adapter = new DeterministicDomainAdapter(['aa', 'bb', 'cc', 'dd']);

        $response = $adapter->get('public/cc/pages/home');

        $this->assertTrue($response['ok']);
        $this->assertSame(['aa' => 'home', 'bb' => 'home', 'cc' => 'home', 'dd' => 'home'], $response['data']['localized_slugs']);
    }
}
