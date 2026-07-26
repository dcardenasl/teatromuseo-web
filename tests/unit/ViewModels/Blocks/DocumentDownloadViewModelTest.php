<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\DocumentDownloadViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentDownloadViewModelTest extends CIUnitTestCase
{
    public function testResolvesConfiguredDocumentReferenceAndDocumentType(): void
    {
        $vm = new DocumentDownloadViewModel([
            'block_config' => [
                'document' => [
                    'source_kind' => 'external_url',
                    'file_id'     => null,
                    'url'         => 'https://example.com/files/handbook.pdf',
                ],
                'open_in_new_tab' => false,
            ],
            'block_data' => [
                'title'       => 'Policy Handbook',
                'description' => 'Internal reference',
                'button_label' => 'Download now',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('https://example.com/files/handbook.pdf', $vars['documentUrl']);
        $this->assertSame('pdf', $vars['docType']);
        $this->assertSame('PDF', $vars['ext']);
        $this->assertFalse($vars['openInNewTab']);
    }

    public function testConfiguredHubDocumentBuildsPreviewUrl(): void
    {
        $vm = new DocumentDownloadViewModel([
            'block_config' => [
                'document' => [
                    'source_kind' => 'hub_file',
                    'file_id'     => 17,
                    'url'         => '/files/17/download?name=handbook.docx',
                ],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('/files/17/download?name=handbook.docx', $vars['documentUrl']);
        $this->assertSame('generic', $vars['docType']);
    }
}
