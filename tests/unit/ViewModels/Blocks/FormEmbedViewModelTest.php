<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\FormEmbedViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormEmbedViewModelTest extends CIUnitTestCase
{
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

    public function testMissingDefinitionDoesNotOpenAServiceRead(): void
    {
        $vm = new FormEmbedViewModel(
            ['block_config' => ['form_key' => 'newsletter']],
            'es'
        );

        $this->assertNull($vm->vars()['formDefinition']);
        $this->assertSame([], $vm->vars()['fields']);
        $this->assertSame('Enviar', $vm->vars()['submitLabel']);
    }

    public function testUnavailableDefinitionDegradesGracefully(): void
    {
        $vars = (new FormEmbedViewModel([], 'es'))->vars();

        $this->assertNull($vars['formDefinition']);
        $this->assertSame([], $vars['fields']);
        $this->assertSame('Enviar', $vars['submitLabel'], 'Default submit label applies');
        $this->assertFalse($vars['hasCaptcha']);
    }

    public function testShowInfoBoxesRespectsExplicitFalse(): void
    {
        $off = new FormEmbedViewModel(['block_config' => ['show_info_boxes' => 'false']], 'es');
        $on  = new FormEmbedViewModel([], 'es');

        $this->assertFalse($off->vars()['showInfoBoxes']);
        $this->assertTrue($on->vars()['showInfoBoxes']);
    }
}
