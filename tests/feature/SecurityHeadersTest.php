<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\HermeticFeatureTestCase;

/**
 * Feature tests for web app security headers.
 *
 * @internal
 */
final class SecurityHeadersTest extends HermeticFeatureTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureLocales(['es', 'en']);
        config('App')->CSPEnabled = true;
    }

    public function testSecurityHeadersArePresent(): void
    {
        // Mock a simple page resolving to make request succeed
        $locale = $this->locale();
        $this->domainAdapter->fakeGet('public/' . $locale . '/collections', []);
        $this->domainAdapter->fakeGet('public-read/' . $locale . '/pages/inicio', [
            'title' => 'Inicio',
            'slug' => 'inicio',
            'page_type' => 'home',
            'excerpt' => 'Excerpt',
            'meta_description' => 'Meta',
            'canonical_url' => '',
            'localized_slugs' => ['es' => 'inicio', 'en' => 'home'],
            'blocks' => [],
        ]);

        $result = $this->get($locale . '/');
        $result->assertStatus(200);

        // Verify standard security headers
        $result->assertHeader('X-Frame-Options', 'DENY');
        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('X-XSS-Protection', '1; mode=block');
        $result->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $result->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()');

        // Verify X-Powered-By is NOT present
        $this->assertFalse($result->response()->hasHeader('X-Powered-By'), 'X-Powered-By header should be removed');

        // Verify the response uses the nonce-based CSP configuration.
        $cspHeader = $result->response()->getHeaderLine('Content-Security-Policy');
        $this->assertNotSame('', $cspHeader);
        $this->assertStringContainsString('base-uri \'self\'', $cspHeader);
        $this->assertStringContainsString('frame-ancestors \'none\'', $cspHeader);
        $this->assertStringNotContainsString("style-src-elem 'self' 'unsafe-inline'", $cspHeader);
        $this->assertStringNotContainsString("script-src-elem 'self' 'unsafe-inline'", $cspHeader);
        $this->assertMatchesRegularExpression('/<style\s+nonce="[^"]+"/', $result->response()->getBody());
    }

    public function testHstsHeaderIsPresentInProduction(): void
    {
        // Force production environment configuration
        $config = config('App');
        $originalForceGlobalSecureRequests = $config->forceGlobalSecureRequests;
        $config->forceGlobalSecureRequests = true;

        $originalEnvServer = $_SERVER['CI_ENVIRONMENT'] ?? 'development';
        $originalEnvEnv = $_ENV['CI_ENVIRONMENT'] ?? 'development';
        $originalEnvGet = getenv('CI_ENVIRONMENT');

        $_SERVER['CI_ENVIRONMENT'] = 'production';
        $_ENV['CI_ENVIRONMENT'] = 'production';
        putenv('CI_ENVIRONMENT=production');

        try {
            // Mock home page
            $locale = $this->locale();
            $this->domainAdapter->fakeGet('public/' . $locale . '/collections', []);
            $this->domainAdapter->fakeGet('public-read/' . $locale . '/pages/inicio', [
                'title' => 'Inicio',
                'slug' => 'inicio',
                'page_type' => 'home',
                'excerpt' => 'Excerpt',
                'meta_description' => 'Meta',
                'canonical_url' => '',
                'localized_slugs' => ['es' => 'inicio', 'en' => 'home'],
                'blocks' => [],
            ]);

            // Access via HTTPS context
            $_SERVER['HTTPS'] = 'on';

            $result = $this->get($locale . '/');
        } finally {
            $config->forceGlobalSecureRequests = $originalForceGlobalSecureRequests;
            $_SERVER['CI_ENVIRONMENT'] = $originalEnvServer;
            $_ENV['CI_ENVIRONMENT'] = $originalEnvEnv;
            if ($originalEnvGet !== false) {
                putenv('CI_ENVIRONMENT=' . $originalEnvGet);
            } else {
                putenv('CI_ENVIRONMENT');
            }
            unset($_SERVER['HTTPS']);
        }

        $result->assertStatus(200);
        $result->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
