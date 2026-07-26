<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\SecurityHeadersFilter;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class SecurityHeadersFilterTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('CSP_IMAGE_SRC=self https://picsum.photos data:');
        putenv('CSP_FRAME_SRC=self https://www.youtube.com https://www.youtube-nocookie.com');
        putenv('CSP_MEDIA_SRC=self https:');
        putenv('CSP_OBJECT_SRC=self https:');

        $_ENV['CSP_IMAGE_SRC'] = 'self https://picsum.photos data:';
        $_ENV['CSP_FRAME_SRC'] = 'self https://www.youtube.com https://www.youtube-nocookie.com';
        $_ENV['CSP_MEDIA_SRC'] = 'self https:';
        $_ENV['CSP_OBJECT_SRC'] = 'self https:';

        Services::reset(true);
    }

    protected function tearDown(): void
    {
        putenv('CSP_IMAGE_SRC');
        putenv('CSP_FRAME_SRC');
        putenv('CSP_MEDIA_SRC');
        putenv('CSP_OBJECT_SRC');

        unset(
            $_ENV['CSP_IMAGE_SRC'],
            $_ENV['CSP_FRAME_SRC'],
            $_ENV['CSP_MEDIA_SRC'],
            $_ENV['CSP_OBJECT_SRC']
        );

        Services::reset(true);

        parent::tearDown();
    }

    public function testAddsCspAllowlistForStarterMedia(): void
    {
        $filter = new SecurityHeadersFilter();
        $request = service('request');
        $response = service('response');

        $filter->after($request, $response);

        $header = $response->getHeaderLine('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' https://picsum.photos data:", $header);
        $this->assertStringContainsString("frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com", $header);
        $this->assertStringContainsString("media-src 'self' https:", $header);
        $this->assertStringContainsString("object-src 'self' https:", $header);
    }
}
