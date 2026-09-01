<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\TeatroEscuelaViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TeatroEscuelaViewModelTest extends CIUnitTestCase
{
    public function testBuildsRegistrationAndVideoPresentationData(): void
    {
        $vm = new TeatroEscuelaViewModel([
            'block_data' => [
                'title' => 'Teatro de Objetos',
                'start_date' => '2099-08-10',
                'registration_url' => 'https://forms.google.com/example',
                'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                'contact_email' => 'escuela@example.org',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('Teatro de Objetos', $vars['title']);
        $this->assertSame('Curso', $vars['activityTypeLabel']);
        $this->assertSame('Inscribirme: Curso', $vars['registerLabel']);
        $this->assertSame('upcoming', $vars['status']);
        $this->assertSame('https://forms.google.com/example', $vars['registrationUrl']);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $vars['videoEmbedUrl']);
        $this->assertSame('escuela@example.org', $vars['contactEmail']);
    }

    public function testSupportsEditorialActivityTypes(): void
    {
        $vars = (new TeatroEscuelaViewModel([
            'block_data' => ['activity_type' => 'workshop', 'registration_url' => 'https://example.org/register'],
        ], 'es'))->vars();

        $this->assertSame('Taller', $vars['activityTypeLabel']);
        $this->assertSame('Inscribirme: Taller', $vars['registerLabel']);
        $this->assertStringContainsString('Taller', $vars['statusLabel']);
    }

    public function testInvalidExternalResourcesAreNotRendered(): void
    {
        $vars = (new TeatroEscuelaViewModel([
            'block_data' => [
                'registration_url' => 'javascript:alert(1)',
                'contact_email' => 'not-an-email',
                'video_url' => 'https://example.org/not-a-provider',
            ],
        ], 'es'))->vars();

        $this->assertSame('', $vars['registrationUrl']);
        $this->assertSame('', $vars['contactEmail']);
        $this->assertSame('', $vars['videoEmbedUrl']);
    }
}
