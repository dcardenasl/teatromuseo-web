<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\DocumentGalleryViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentGalleryViewModelTest extends CIUnitTestCase
{
    public function testUsesCanonicalFileReferencesInsideRepeaterItems(): void
    {
        $vm = new DocumentGalleryViewModel([
            'block_config' => [
                'layout' => 'simple_list',
                'show_file_meta' => true,
            ],
            'block_data' => [
                'documents' => [
                    [
                        'file' => [
                            'source_kind' => 'external_url',
                            'file_id'     => null,
                            'url'         => 'https://example.com/files/policies.pdf',
                        ],
                        'title' => 'Policies',
                        'description' => 'Main handbook',
                    ],
                    [
                        'file' => [
                            'source_kind' => 'external_url',
                            'file_id'     => null,
                            'url'         => 'https://example.com/files/guide.docx',
                        ],
                        'title' => 'Guide',
                    ],
                ],
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('simple_list', $vars['layout']);
        $this->assertCount(2, $vars['documents']);
        $this->assertSame('https://example.com/files/policies.pdf', $vars['documents'][0]['fileUrl']);
        $this->assertSame('pdf', $vars['documents'][0]['docType']);
        $this->assertSame('word', $vars['documents'][1]['docType']);
    }
}
