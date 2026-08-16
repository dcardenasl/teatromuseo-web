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
        config('App')->pageDeliveryMode = 'sync';
    }

    public function testHomepageUsesSynchronousPageDeliveryWithoutRepeatedLayoutReads(): void
    {
        $result = $this->get('aa/');

        $result->assertStatus(200);
        $result->assertSee('Fixture homepage aa');

        $paths = $this->domainAdapter->requestedPaths();
        // WEB-PAGE-01 composes the homepage through the BFF in one request.
        $this->assertSame(1, count(array_filter($paths, static fn (string $path): bool => $path === 'public-read/aa/page-resolve/home')));
        $this->assertNotContains('public-read/aa/layout', $paths);
        $this->assertNotContains('public-read/aa/pages/inicio', $paths);
        $this->assertNotContains('public/aa/forms/contact', $paths);
    }
}
