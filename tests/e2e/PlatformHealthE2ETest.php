<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlatformHealthE2ETest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function endpointProvider(): iterable
    {
        yield 'api' => ['http://127.0.0.1:8180/ready'];
        yield 'admin' => ['http://127.0.0.1:8182/health'];
        yield 'web' => ['http://127.0.0.1:8186/health'];
        yield 'domain' => ['http://127.0.0.1:8190/ready'];
    }

    #[DataProvider('endpointProvider')]
    public function testPlatformEndpointIsHealthy(string $url): void
    {
        if (getenv('RUN_E2E_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_E2E_TESTS=1 after starting the root platform stack.');
        }

        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = @file_get_contents($url, false, $context);

        $this->assertNotFalse($body, $url . ' is unreachable.');
        $headers = $http_response_header ?? [];
        $this->assertMatchesRegularExpression('#^HTTP/\S+ 200\b#', $headers[0] ?? '');
    }
}
