<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\TeamMemberViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class TeamMemberViewModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        service('request')->setLocale('es');
    }

    public function testBuildsTeamMemberVariables(): void
    {
        $vm = new TeamMemberViewModel([
            'block_config' => [
                'photo' => [
                    'source_kind' => 'external_url',
                    'file_id'     => null,
                    'url'         => 'https://cdn.test/john.jpg',
                ],
            ],
            'block_data' => [
                'name'         => 'John Doe',
                'position'     => 'Developer',
                'bio'          => 'Short bio',
                'linkedin_url' => 'https://linkedin.com/in/johndoe',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertSame('John Doe', $vars['name']);
        $this->assertSame('Developer', $vars['position']);
        $this->assertSame('Short bio', $vars['bio']);
        $this->assertSame('https://cdn.test/john.jpg', $vars['photo']['url']);
        $this->assertSame('https://linkedin.com/in/johndoe', $vars['linkedin']);
    }

    public function testLocalizesRelativeLinkedinUrl(): void
    {
        $vm = new TeamMemberViewModel([
            'block_data' => [
                'name'         => 'John Doe',
                'position'     => 'Developer',
                'linkedin_url' => '/some-internal-link',
            ],
        ], 'es');

        $vars = $vm->vars();

        $this->assertStringContainsString('/es/some-internal-link', $vars['linkedin']);
    }
}
