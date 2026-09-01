<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\PdfViewerViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PdfViewerViewModelTest extends CIUnitTestCase
{
    public function testResolvesConfiguredPdfReferenceAndDefaults(): void
    {
        $vm = new PdfViewerViewModel([
            'block_config' => [
                'pdf_file' => [
                    'source_kind' => 'external_url',
                    'file_id'     => null,
                    'url'         => 'https://example.com/files/manual.pdf',
                ],
                'height'   => '800px',
                'allow_download' => false,
            ],
            'block_data' => [
                'heading' => 'Manual',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('https://example.com/files/manual.pdf', $vars['pdfUrl']);
        $this->assertSame('external_url', $vars['pdfFile']['source_kind']);
        $this->assertSame('800px', $vars['height']);
        $this->assertFalse($vars['allowDownload']);
    }

    public function testConfiguredHubPdfReferenceWithoutUrlStaysUnresolved(): void
    {
        $vm = new PdfViewerViewModel([
            'block_config' => [
                'pdf_file' => [
                    'source_kind' => 'hub_file',
                    'file_id'     => 42,
                    'url'         => '',
                ],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('', $vars['pdfUrl']);
    }
}
