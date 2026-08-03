<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Blocks;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionGridViewTest extends CIUnitTestCase
{
    public function testCollectionGridHonorsConfiguredAspectRatio(): void
    {
        $html = view('blocks/collection_grid', [
            'sectionTitle' => 'Noticias',
            'sectionSubtitle' => '',
            'viewAllLabel' => '',
            'emptyMessage' => '',
            'collectionKey' => 'noticias',
            'layoutVariant' => 'cards',
            'imageAspectRatio' => '3/4',
            'imageAspectRatioClass' => 'aspect-[3/4]',
            'cssClass' => '',
            'canonicalViewAllUrl' => '/noticias',
            'entries' => [[
                'title' => 'Entrada de prueba',
                'excerpt' => 'Resumen de prueba',
                'published_at' => '2026-08-01 10:00:00',
                'slug' => 'entrada-de-prueba',
                'featured_image' => [
                    'url' => 'https://example.com/entrada.jpg',
                    'variants' => [],
                ],
            ]],
            'sectionClass' => 'section',
            'containerClass' => 'container-base',
            'gridClass' => 'grid gap-6 md:grid-cols-3',
            'lang' => 'es',
        ], ['saveData' => false]);

        $this->assertStringContainsString('aspect-[3/4]', $html);
        $this->assertStringNotContainsString('aspect-video', $html);
    }
}
