<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;

final class DomainPublicApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_CONTRACT_TESTS=1 and provide an isolated Domain endpoint.');
        }
    }

    public function testPublishedPagesCollectionUsesTheWebEnvelope(): void
    {
        [$status, $contentType, $body] = $this->get('/api/v1/public/es/pages');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('application/json', $contentType);

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertSame('success', $payload['status'] ?? null);
        $this->assertIsArray($payload['data'] ?? null);
    }

    /** @return array{int, string, string} */
    private function get(string $path): array
    {
        $baseUrl = rtrim((string) (getenv('DOMAIN_CONTRACT_BASE_URL') ?: ''), '/');
        $this->assertNotSame('', $baseUrl, 'DOMAIN_CONTRACT_BASE_URL is required.');

        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
        $body = @file_get_contents($baseUrl . $path, false, $context);
        $this->assertNotFalse($body, 'Domain contract endpoint is unreachable.');

        $headers = $http_response_header ?? [];
        preg_match('#\s(\d{3})\s#', $headers[0] ?? '', $statusMatch);
        $contentType = '';
        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), 'content-type:')) {
                $contentType = trim(substr($header, strlen('content-type:')));
                break;
            }
        }

        return [(int) ($statusMatch[1] ?? 0), $contentType, $body];
    }
}
