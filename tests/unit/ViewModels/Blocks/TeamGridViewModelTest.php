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
        $this->assertSame('Dirección', $vars['members'][0]['position']);
        $this->assertSame('Gestión cultural', $vars['members'][0]['profession']);
        $this->assertSame('https://cdn.test/front.jpg', $vars['members'][0]['image']['url']);
        $this->assertSame('https://cdn.test/hover.jpg', $vars['members'][0]['hover_image']['url']);
        $this->assertSame('equipo@example.test', $vars['members'][0]['email']);
    }

    public function testUsesResolvedHoverMediaWhenPrimaryPortraitIsLegacy(): void
    {
        $vars = (new TeamGridViewModel([
            'children' => [[
                'block_key' => 'team_member',
                'block_data' => ['name' => 'Víctor Quiroga'],
                'block_config' => [
                    'photo' => ['source_kind' => 'external_url', 'url' => '/images/team/victor-quiroga.png'],
                    'hover_photo' => [
                        'source_kind' => 'hub_file',
                        'file_id' => 1724,
                        'url' => 'https://api.teatromuseo.cl/uploads/2026/08/04/victor_sm.webp',
                    ],
                ],
            ]],
        ], 'es'))->vars();

        $this->assertSame(
            'https://api.teatromuseo.cl/uploads/2026/08/04/victor_sm.webp',
            $vars['members'][0]['image']['url'],
        );
    }

    public function testDoesNotReplaceLegacyPortraitWhenHoverMediaIsAlsoLegacy(): void
    {
        $vars = (new TeamGridViewModel([
            'children' => [[
                'block_key' => 'team_member',
                'block_data' => ['name' => 'Persona editorial'],
                'block_config' => [
                    'photo' => ['url' => '/images/team/front.png'],
                    'hover_photo' => ['url' => '/images/team/hover.png'],
                ],
            ]],
        ], 'es'))->vars();

        $this->assertSame('/images/team/front.png', $vars['members'][0]['image']['url']);
    }
}
