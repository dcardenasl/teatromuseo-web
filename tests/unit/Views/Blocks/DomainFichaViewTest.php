<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DomainFichaViewTest extends CIUnitTestCase
{
    public function testStructuredFieldsUseSpanishLabelsAndValues(): void
    {
        service('language')->setLocale('es');

        $html = view('blocks/domain_ficha', [
            'block' => ['block_key' => 'curso_ficha'],
            'data' => [
                'modality' => 'presencial',
                'start_date' => '2026-08-10',
                'publication_type' => 'editorial',
            ],
            'renderedChildren' => '',
        ], ['saveData' => false]);

        $this->assertStringContainsString('Modalidad', $html);
        $this->assertStringContainsString('Presencial', $html);
        $this->assertStringContainsString('Inicio', $html);
        $this->assertStringContainsString('Tipo', $html);
        $this->assertStringContainsString('Editorial', $html);
        $this->assertStringNotContainsString('Start date', $html);
        $this->assertStringNotContainsString('In person', $html);
    }

    public function testStructuredFieldsUseEnglishLabelsAndValues(): void
    {
        service('language')->setLocale('en');

        $html = view('blocks/domain_ficha', [
            'block' => ['block_key' => 'course_profile'],
            'data' => [
                'modality' => 'presencial',
                'start_date' => '2026-08-10',
                'publication_type' => 'editorial',
            ],
            'renderedChildren' => '',
        ], ['saveData' => false]);

        $this->assertStringContainsString('Format', $html);
        $this->assertStringContainsString('In person', $html);
        $this->assertStringContainsString('Start', $html);
        $this->assertStringContainsString('Editorial', $html);
    }
}
