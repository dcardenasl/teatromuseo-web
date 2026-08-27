<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\SiteFormService;
use PHPUnit\Framework\TestCase;

/**
 * `getDefinition()` and `submit()` deliberately use two different clients:
 * the BFF has a direct-DB public-read seam for form *definitions*, but no
 * route at all for `public/submissions` (only the Domain validates, verifies
 * CAPTCHA, and dispatches email jobs) — so submissions must go straight to
 * the Domain's own `webappkey`-gated endpoint instead.
 *
 * @internal
 */
final class SiteFormServiceTest extends TestCase
{
    public function testGetDefinitionUsesTheBffClient(): void
    {
        $bffClient    = $this->createMock(WebApiClientInterface::class);
        $domainClient = $this->createMock(WebApiClientInterface::class);

        $definition = ['form_key' => 'contact', 'fields' => []];
        $bffClient->expects($this->once())
            ->method('get')
            ->with('public/es/forms/contact', [], 300, 'forms')
            ->willReturn(['ok' => true, 'data' => $definition]);
        $domainClient->expects($this->never())->method('get');
        $domainClient->expects($this->never())->method('post');

        $service = new SiteFormService($bffClient, $domainClient);

        $this->assertSame($definition, $service->getDefinition('es', 'contact'));
    }

    public function testSubmitUsesTheDomainClientDirectlyNotTheBff(): void
    {
        $bffClient    = $this->createMock(WebApiClientInterface::class);
        $domainClient = $this->createMock(WebApiClientInterface::class);

        $bffClient->expects($this->never())->method('post');
        $domainClient->expects($this->once())
            ->method('post')
            ->with('public/submissions', [
                'form_key'  => 'contact',
                'form_data' => ['email' => 'ada@example.com'],
            ])
            ->willReturn(['ok' => true, 'data' => ['id' => 42]]);

        $service = new SiteFormService($bffClient, $domainClient);
        $result  = $service->submit('contact', ['email' => 'ada@example.com']);

        $this->assertSame(['ok' => true, 'id' => 42, 'messages' => []], $result);
    }

    public function testSubmitIncludesCaptchaTokenWhenProvided(): void
    {
        $bffClient    = $this->createMock(WebApiClientInterface::class);
        $domainClient = $this->createMock(WebApiClientInterface::class);

        $domainClient->expects($this->once())
            ->method('post')
            ->with('public/submissions', [
                'form_key'      => 'contact',
                'form_data'     => ['email' => 'ada@example.com'],
                'captcha_token' => 'token-123',
            ])
            ->willReturn(['ok' => true, 'data' => ['id' => 1]]);

        (new SiteFormService($bffClient, $domainClient))
            ->submit('contact', ['email' => 'ada@example.com'], 'token-123');
    }

    public function testSubmitReturnsFailureMessagesWhenDomainRejects(): void
    {
        $bffClient    = $this->createMock(WebApiClientInterface::class);
        $domainClient = $this->createMock(WebApiClientInterface::class);

        $domainClient->method('post')->willReturn([
            'ok' => false,
            'data' => null,
            'messages' => ['Ha ocurrido un error interno en el servidor.'],
        ]);

        $result = (new SiteFormService($bffClient, $domainClient))->submit('contact', []);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['id']);
        $this->assertSame(['Ha ocurrido un error interno en el servidor.'], $result['messages']);
    }
}
