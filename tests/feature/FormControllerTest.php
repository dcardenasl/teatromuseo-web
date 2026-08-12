<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SiteFormService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\Mock\MockCache;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * @internal
 */
final class FormControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        Services::resetSingle('siteFormService');
        Services::resetSingle('cache');
        Services::resetSingle('security');
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        $this->csrfToken = bin2hex(random_bytes(16));
    }

    protected function tearDown(): void
    {
        Services::resetSingle('siteFormService');
        Services::resetSingle('cache');
        Services::resetSingle('security');
        $_COOKIE = [];
        service('superglobals')->setCookieArray([]);
        $this->disableThrottleInTests();

        parent::tearDown();
    }

    public function testHoneypotFilledRedirectsSilentlyWithoutSubmitting(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key' => 'contact',
                'fields'   => [
                    [
                        'field_key'   => 'email',
                        'field_type'  => 'email',
                        'is_required' => 1,
                    ],
                ],
            ]);
        $formService->expects($this->never())->method('submit');

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'email'   => 'bot@example.com',
                'website' => 'https://spam.example',
            ]);

        $result->assertStatus(302);
        $this->assertTrue(session()->getFlashdata('form_success_contact'));
    }

    public function testSubmitValidationFailsWhenCaptchaIsRequiredButMissing(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key'    => 'contact',
                'has_captcha' => 1,
                'fields'      => [
                    [
                        'field_key'   => 'email',
                        'field_type'  => 'email',
                        'is_required' => 1,
                    ],
                ],
            ]);
        $formService->expects($this->never())->method('submit');

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'email' => 'ada@example.com',
            ]);

        $result->assertStatus(302);
        $errors = session()->getFlashdata('form_errors_contact');
        $this->assertArrayHasKey('_captcha', $errors);
    }

    public function testSubmitValidationFailsWhenRequiredFieldIsEmpty(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key' => 'contact',
                'fields'   => [
                    [
                        'field_key'      => 'name',
                        'field_type'     => 'text',
                        'is_required'    => 1,
                        'error_required' => 'Nombre es requerido.',
                    ],
                ],
            ]);

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'name' => '',
            ]);

        $result->assertStatus(302);
        $errors = session()->getFlashdata('form_errors_contact');
        $this->assertArrayHasKey('name', $errors);
        $this->assertSame('Nombre es requerido.', $errors['name']);
    }

    public function testSubmitValidationFailsWhenEmailIsInvalid(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key' => 'contact',
                'fields'   => [
                    [
                        'field_key'     => 'email',
                        'field_type'    => 'email',
                        'is_required'   => 1,
                        'error_invalid' => 'Email no válido.',
                    ],
                ],
            ]);

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'email' => 'not-an-email',
            ]);

        $result->assertStatus(302);
        $errors = session()->getFlashdata('form_errors_contact');
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('Email no válido.', $errors['email']);
    }

    public function testSubmitSuccess(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key' => 'contact',
                'fields'   => [
                    [
                        'field_key'   => 'name',
                        'field_type'  => 'text',
                        'is_required' => 1,
                    ],
                    [
                        'field_key'   => 'email',
                        'field_type'  => 'email',
                        'is_required' => 1,
                    ],
                ],
            ]);

        $formService->expects($this->once())
            ->method('submit')
            ->with(
                'contact',
                [
                    'name'  => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                ],
                null
            )
            ->willReturn([
                'ok'       => true,
                'id'       => 123,
                'messages' => [],
            ]);

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'name'  => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ]);

        $result->assertStatus(302);
        $this->assertTrue(session()->getFlashdata('form_sent_contact'));
    }

    public function testSubmitPassesCaptchaTokenWhenCaptchaIsRequired(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'contact')
            ->willReturn([
                'form_key'    => 'contact',
                'has_captcha' => 1,
                'fields'      => [
                    [
                        'field_key'   => 'email',
                        'field_type'  => 'email',
                        'is_required' => 1,
                    ],
                ],
            ]);

        $formService->expects($this->once())
            ->method('submit')
            ->with('contact', ['email' => 'ada@example.com'], 'captcha-token')
            ->willReturn([
                'ok'       => true,
                'id'       => 123,
                'messages' => [],
            ]);

        Services::injectMock('siteFormService', $formService);

        $result = $this->postForm('forms/contact/submit', [
                'email'                => 'Ada@Example.com',
                'g_recaptcha_response' => 'captcha-token',
            ]);

        $result->assertStatus(302);
        $this->assertTrue(session()->getFlashdata('form_sent_contact'));
    }

    public function testSubmitIsThrottledAfterRouteLimit(): void
    {
        $this->enableThrottleInTests();
        Services::injectMock('cache', new MockCache());

        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->any())
            ->method('getDefinition')
            ->willReturn([
                'form_key' => 'contact',
                'fields'   => [
                    [
                        'field_key'   => 'email',
                        'field_type'  => 'email',
                        'is_required' => 1,
                    ],
                ],
            ]);
        $formService->expects($this->any())
            ->method('submit')
            ->willReturn([
                'ok'       => true,
                'id'       => 123,
                'messages' => [],
            ]);

        Services::injectMock('siteFormService', $formService);

        $result = null;
        for ($i = 0; $i < 11; $i++) {
            $result = $this->postForm('forms/contact/submit', [
                        'email' => "ada{$i}@example.com",
                    ]);
        }

        $this->assertNotNull($result);
        $result->assertStatus(429);
    }

    public function testSubmitRejectsMissingCsrfBeforeLoadingFormDefinition(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->never())->method('getDefinition');
        Services::injectMock('siteFormService', $formService);

        $this->expectException(SecurityException::class);

        $result = $this->withHeaders(['Referer' => 'http://localhost:8184/contacto'])
            ->post('forms/contact/submit', ['email' => 'ada@example.com']);
    }

    public function testSubmitRejectsMismatchedCsrfBeforeLoadingFormDefinition(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->never())->method('getDefinition');
        Services::injectMock('siteFormService', $formService);

        $securityConfig = config('Security');
        $_COOKIE[$securityConfig->cookieName] = $this->csrfToken;
        service('superglobals')->setCookieArray($_COOKIE);
        Services::resetSingle('incomingrequest');
        Services::resetSingle('security');

        $this->expectException(SecurityException::class);

        $result = $this->withHeaders([
            'Referer'                  => 'http://localhost:8184/contacto',
            $securityConfig->headerName => bin2hex(random_bytes(16)),
        ])->post('forms/contact/submit', ['email' => 'ada@example.com']);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function postForm(string $path, array $params): TestResponse
    {
        $securityConfig = config('Security');
        $_COOKIE[$securityConfig->cookieName] = $this->csrfToken;
        service('superglobals')->setCookieArray($_COOKIE);
        Services::resetSingle('incomingrequest');
        Services::resetSingle('security');

        $result = $this->withHeaders([
            'Referer'                  => 'http://localhost:8184/contacto',
            $securityConfig->headerName => $this->csrfToken,
        ])->post($path, $params);

        $rotatedCookie = $result->response()->getCookie($securityConfig->cookieName);
        if ($rotatedCookie !== null) {
            $this->csrfToken = $rotatedCookie->getValue();
        }

        return $result;
    }

    private function enableThrottleInTests(): void
    {
        putenv('WEB_THROTTLE_IN_TESTS=true');
        $_ENV['WEB_THROTTLE_IN_TESTS']    = 'true';
        $_SERVER['WEB_THROTTLE_IN_TESTS'] = 'true';
    }

    private function disableThrottleInTests(): void
    {
        putenv('WEB_THROTTLE_IN_TESTS');
        unset($_ENV['WEB_THROTTLE_IN_TESTS'], $_SERVER['WEB_THROTTLE_IN_TESTS']);
    }
}
