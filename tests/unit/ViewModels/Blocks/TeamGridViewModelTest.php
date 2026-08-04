<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\TeamGridViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TeamGridViewModelTest extends CIUnitTestCase
{
    public function testRendersTheCmsChildrenAndTheirHoverMedia(): void
    {
        $vars = (new TeamGridViewModel([
            'block_config' => ['items_limit' => 10],
            'children' => [[
                'block_key' => 'team_member',
                'block_data' => [
                    'name' => 'Persona editorial',
                    'position' => 'Dirección',
                    'profession' => 'Gestión cultural',
                    'email' => 'equipo@example.test',
                ],
                'block_config' => [
                    'photo' => ['url' => 'https://cdn.test/front.jpg'],
                    'hover_photo' => ['url' => 'https://cdn.test/hover.jpg'],
                ],
            ]],
        ], 'es'))->vars();

        $this->assertCount(1, $vars['members']);
        $this->assertSame('Persona editorial', $vars['members'][0]['title']);
        $this->assertSame('https://cdn.test/front.jpg', $vars['members'][0]['image']['url']);
        $this->assertSame('https://cdn.test/hover.jpg', $vars['members'][0]['hover_image']['url']);
        $this->assertSame('equipo@example.test', $vars['members'][0]['email']);
    }
}
