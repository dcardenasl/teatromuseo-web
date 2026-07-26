<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\Services\SiteFormService;
use App\Services\SiteSettingsService;
use App\ViewModels\Blocks\FormEmbedViewModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * @internal
 */
final class FormEmbedViewModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settings = $this->createMock(SiteSettingsService::class);
        $settings->method('get')->willReturn('');
        Services::injectMock('siteSettingsService', $settings);
    }

    protected function tearDown(): void
    {
        Services::reset(true);

        parent::tearDown();
    }

    public function testUsesInjectedFormDefinitionFromContext(): void
    {
        $definition = [
            'fields'          => [['field_key' => 'name', 'field_type' => 'text']],
            'submit_label'    => 'Enviar ahora',
            'success_message' => 'Gracias',
            'has_captcha'     => 1,
        ];

        $vm = new FormEmbedViewModel(
            ['block_config' => ['form_key' => 'contact']],
            'es',
            ['formDefinition' => $definition]
        );

        $vars = $vm->vars();

        $this->assertSame('contact', $vars['formKey']);
        $this->assertSame($definition['fields'], $vars['fields']);
        $this->assertSame('Enviar ahora', $vars['submitLabel']);
        $this->assertSame('Gracias', $vars['successMsg']);
        $this->assertTrue($vars['hasCaptcha']);
    }

    public function testFallsBackToServiceWhenDefinitionNotInjected(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->expects($this->once())
            ->method('getDefinition')
            ->with('es', 'newsletter')
            ->willReturn(['fields' => [], 'submit_label' => 'Suscribirme']);
        Services::injectMock('siteFormService', $formService);

        $vm = new FormEmbedViewModel(
            ['block_config' => ['form_key' => 'newsletter']],
            'es'
        );

        $this->assertSame('Suscribirme', $vm->vars()['submitLabel']);
    }

    public function testUnavailableDefinitionDegradesGracefully(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->method('getDefinition')->willReturn(null);
        Services::injectMock('siteFormService', $formService);

        $vars = (new FormEmbedViewModel([], 'es'))->vars();

        $this->assertNull($vars['formDefinition']);
        $this->assertSame([], $vars['fields']);
        $this->assertSame('Enviar', $vars['submitLabel'], 'Default submit label applies');
        $this->assertFalse($vars['hasCaptcha']);
    }

    public function testShowInfoBoxesRespectsExplicitFalse(): void
    {
        $formService = $this->createMock(SiteFormService::class);
        $formService->method('getDefinition')->willReturn(null);
        Services::injectMock('siteFormService', $formService);

        $off = new FormEmbedViewModel(['block_config' => ['show_info_boxes' => 'false']], 'es');
        $on  = new FormEmbedViewModel([], 'es');

        $this->assertFalse($off->vars()['showInfoBoxes']);
        $this->assertTrue($on->vars()['showInfoBoxes']);
    }
}
