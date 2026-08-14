<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\WebApiClientInterface;
use App\Services\PublicReadDiagnosticsService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/** @internal */
final class DiagnosticsControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const VALID_KEY = 'test-public-read-diagnostics-key';

    protected function tearDown(): void
    {
        Services::resetSingle('publicReadDiagnostics');
        putenv('PUBLIC_READ_DIAGNOSTICS_KEY');
        unset($_ENV['PUBLIC_READ_DIAGNOSTICS_KEY'], $_SERVER['PUBLIC_READ_DIAGNOSTICS_KEY']);
        parent::tearDown();
    }

    public function testReturns404WhenDiagnosticsAreDisabled(): void
    {
        $this->unsetKey();

        $this->get('diagnostics/public-read')->assertStatus(404);
    }

    public function testReturns401WhenDiagnosticsKeyDoesNotMatch(): void
    {
        $this->setKey(self::VALID_KEY);

        $this->withHeaders(['X-Diagnostics-Key' => 'wrong-key'])
            ->get('diagnostics/public-read')
            ->assertStatus(401);
    }

    public function testReturnsDiagnosticEnvelopeWithValidKey(): void
    {
        $this->setKey(self::VALID_KEY);
        $client = $this->createMock(WebApiClientInterface::class);
        $client->method('get')->willReturn([
            'ok'       => true,
            'status'   => 200,
            'data'     => [
                'status' => 'healthy',
                'checks' => [
                    'databases' => [
                        'cms'     => ['status' => 'healthy', 'response_time_ms' => 1.1],
                        'catalog' => ['status' => 'healthy', 'response_time_ms' => 1.2],
                        'event'   => ['status' => 'healthy', 'response_time_ms' => 1.3],
                    ],
                ],
            ],
            'meta'     => [],
            'messages' => [],
        ]);
        Services::injectMock(
            'publicReadDiagnostics',
            new PublicReadDiagnosticsService($client),
        );

        $result = $this->withHeaders(['X-Diagnostics-Key' => self::VALID_KEY])
            ->get('diagnostics/public-read');

        $result->assertStatus(200);
        $this->assertStringContainsString(
            'no-store',
            $result->response()->getHeaderLine('Cache-Control'),
        );
        $payload = json_decode((string) $result->response()->getBody(), true);

        $this->assertIsArray($payload);
        $this->assertSame('public-read-diagnostics.v1', $payload['schema'] ?? null);
        $this->assertArrayHasKey('web', $payload);
        $this->assertArrayHasKey('domains', $payload);
        $this->assertSame('healthy', $payload['domains']['cms']['status'] ?? null);
        $this->assertArrayHasKey('provider_visibility', $payload);
    }

    private function setKey(string $value): void
    {
        putenv('PUBLIC_READ_DIAGNOSTICS_KEY=' . $value);
        $_ENV['PUBLIC_READ_DIAGNOSTICS_KEY'] = $value;
        $_SERVER['PUBLIC_READ_DIAGNOSTICS_KEY'] = $value;
    }

    private function unsetKey(): void
    {
        putenv('PUBLIC_READ_DIAGNOSTICS_KEY');
        unset($_ENV['PUBLIC_READ_DIAGNOSTICS_KEY'], $_SERVER['PUBLIC_READ_DIAGNOSTICS_KEY']);
    }
}
