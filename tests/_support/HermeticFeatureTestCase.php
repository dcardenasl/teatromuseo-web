<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Tests\Support\Libraries\DeterministicDomainAdapter;

abstract class HermeticFeatureTestCase extends CIUnitTestCase
{
    protected DeterministicDomainAdapter $domainAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        Services::reset(true);
        $config = config('App');
        $config->supportedLocales = ['es', 'en'];
        $config->defaultLocale = 'es';
        $this->domainAdapter = new DeterministicDomainAdapter();
        Services::injectMock('webApiClient', $this->domainAdapter);
    }

    protected function locale(int $position = 0): string
    {
        return $this->domainAdapter->locales()[$position];
    }

    /** @param list<string> $locales */
    protected function configureLocales(array $locales): void
    {
        $this->assertNotEmpty($locales);
        config('App')->supportedLocales = array_values($locales);
        config('App')->defaultLocale = $locales[0];
        $this->domainAdapter = new DeterministicDomainAdapter($locales);
        Services::injectMock('webApiClient', $this->domainAdapter);
    }

    /** @return list<string> */
    protected function locales(): array
    {
        return $this->domainAdapter->locales();
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }
}
