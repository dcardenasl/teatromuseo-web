<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Feature tests for BlockPreviewController.
 *
 * @internal
 */
final class BlockPreviewControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testBlockPreviewReturnsHtml(): void
    {
        $payload = [
            'block_key'    => 'hero_banner',
            'block_config' => json_encode(['css_class' => 'custom-hero']),
            'block_data'   => json_encode(['heading' => 'Hello World', 'subheading' => 'This is a test banner']),
            'preview_mode' => 'live',
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('html', $json);
        $this->assertStringContainsString('Hello World', $json['html']);
        $this->assertStringContainsString('This is a test banner', $json['html']);
        $this->assertStringContainsString('custom-hero', $json['html']);
    }

    public function testBlockPreviewUsesLiveModeWithoutMockContent(): void
    {
        $payload = [
            'block_key'    => 'hero_banner',
            'block_config' => json_encode(['css_class' => 'custom-hero']),
            'block_data'   => json_encode([
                'heading' => 'Live Hero Title',
                'subheading' => 'Live hero subtitle',
            ]),
            'preview_mode' => 'live',
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertStringContainsString('Live Hero Title', $json['html']);
        $this->assertStringContainsString('Live hero subtitle', $json['html']);
        $this->assertStringNotContainsString('Previsualización de Banner', $json['html']);
        $this->assertStringNotContainsString('photo-1507525428034-b723cf961d3e', $json['html']);
    }

    public function testBlockPreviewMocksEmptyHeroBanner(): void
    {
        $payload = [
            'block_key'    => 'hero_banner',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('Previsualización de Banner', $json['html']);
        $this->assertStringContainsString('Este banner utiliza las tipografías y el diseño completo de tu sitio público.', $json['html']);
        $this->assertStringContainsString('photo-1507525428034-b723cf961d3e', $json['html']); // Unsplash photo ID in mock
    }

    public function testBlockPreviewMocksEmptyAlert(): void
    {
        $payload = [
            'block_key'    => 'alert',
            'block_config' => json_encode(['type' => 'success']),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('Aviso Importante', $json['html']);
        $this->assertStringContainsString('Este es un mensaje de alerta de ejemplo para mostrar cómo se ve el diseño en tu sitio público.', $json['html']);
    }

    public function testBlockPreviewMocksEmptyVideoPlayer(): void
    {
        $payload = [
            'block_key'    => 'video_player',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('Video de Presentación de Ejemplo', $json['html']);
        $this->assertStringContainsString('photo-1618005182384-a83a8bd57fbe', $json['html']); // Unsplash poster ID in mock
    }

    public function testBlockPreviewMocksEmptyPdfViewerWithLocalAsset(): void
    {
        $payload = [
            'block_key'    => 'pdf_viewer',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertStringContainsString('assets/docs/policies-handbook-demo.pdf', $json['html']);
    }

    public function testBlockPreviewMocksEmptyMapEmbed(): void
    {
        $payload = [
            'block_key'    => 'map_embed',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('Nuestra Ubicación', $json['html']);
        $this->assertStringContainsString('Valparaíso, Chile', $json['html']);
        $this->assertStringContainsString('google.com/maps/embed', $json['html']);
    }

    public function testBlockPreviewMocksEmptyRichText(): void
    {
        $payload = [
            'block_key'    => 'rich_text',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('bloque de texto enriquecido de ejemplo', $json['html']);
    }

    public function testBlockPreviewMocksEmptyAccordion(): void
    {
        $payload = [
            'block_key'    => 'accordion',
            'block_config' => json_encode([]),
            'block_data'   => json_encode([]),
        ];

        $result = $this->post('blocks/preview', $payload);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('¿Cómo funciona la vista previa?', $json['html']);
        $this->assertStringContainsString('¿Es fiel al diseño final?', $json['html']);
    }
}
