<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CollectionEntryCardTest extends CIUnitTestCase
{
    public function testEntryCardRendersCanonicalFeaturedImageReference(): void
    {
        $html = view('collection/partials/entry_card', [
            'entry' => [
                'slug' => 'proyecto-x',
                'title' => 'Proyecto X',
                'excerpt' => 'Resumen breve',
                'featured_image' => [
                    'source_kind' => 'external_url',
                    'url' => 'https://cdn.example.com/proyecto-x.jpg',
                ],
                'categories' => [
                    ['name' => 'Desarrollo Web'],
                ],
            ],
            'collectionUrlPath' => '/portafolio',
            'lang' => 'es',
        ]);

        $this->assertStringContainsString('https://cdn.example.com/proyecto-x.jpg', $html);
        $this->assertStringContainsString('Proyecto X', $html);
    }
}
