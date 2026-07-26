<?php

declare(strict_types=1);

namespace Tests\Unit\Views\Layouts;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HeadPartialTest extends CIUnitTestCase
{
    public function testHeadPartialBuildsDefaultSchemaWhenSchemaDataIsMissing(): void
    {
        $html = view('layouts/partials/head', [
            'settings' => [],
        ]);

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('https://schema.org', $html);
        $this->assertStringContainsString('"@type":"WebPage"', $html);
    }
}
