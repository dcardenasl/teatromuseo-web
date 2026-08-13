<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

final class PageDeliveryHomepageTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['aa', 'bb']);
        config('App')->pageDeliveryEnabled = true;
        config('App')->pageDeliveryMode = 'sync';
    }

    public function testHomepageUsesSynchronousPageDeliveryWithoutRepeatedLayoutReads(): void
    {
        $result = $this->get('aa/');

        $result->assertStatus(200);
        $result->assertSee('Fixture homepage aa');

        $paths = $this->domainAdapter->requestedPaths();
        // Navigation, collections and settings are one composite request
        // since ADR 006 (teatromuseo-cms-domain) — was three separate reads.
        $this->assertSame(1, count(array_filter($paths, static fn (string $path): bool => $path === 'public-read/aa/layout')));
        $this->assertSame(1, count(array_filter($paths, static fn (string $path): bool => str_ends_with($path, '/pages/inicio'))));
        $this->assertNotContains('public/aa/forms/contact', $paths);
    }
}
