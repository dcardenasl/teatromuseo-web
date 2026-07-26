<?php

declare(strict_types=1);

namespace Tests\Unit\ViewModels\Blocks;

use App\ViewModels\Blocks\MetricsGridViewModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class MetricsGridViewModelTest extends CIUnitTestCase
{
    public function testBuildsStatsWithDerivedCounterValues(): void
    {
        $vm = new MetricsGridViewModel([
            'children' => [
                [
                    'block_key'  => 'metric_item',
                    'block_data' => ['prefix' => '+', 'number' => '150k', 'label' => 'Usuarios'],
                ],
            ],
        ], 'es');

        $stats = $vm->vars()['stats'];

        $this->assertCount(1, $stats);
        $this->assertSame(150, $stats[0]['num_only']);
        $this->assertSame('k', $stats[0]['display_suffix'], 'Suffix falls back to non-numeric chars of number');
        $this->assertSame('+150k', $stats[0]['display_value']);
    }

    public function testNonMetricChildrenAreIgnored(): void
    {
        $vm = new MetricsGridViewModel([
            'children' => [
                ['block_key' => 'slide_card', 'block_data' => ['number' => '10']],
            ],
        ], 'es');

        $this->assertSame([], $vm->vars()['stats']);
    }

    public function testVariantClassMapping(): void
    {
        $dark = (new MetricsGridViewModel([
            'block_config' => ['variant' => 'dark'],
            'children'     => [['block_key' => 'metric_item', 'block_data' => ['number' => '1']]],
        ], 'es'))->vars();

        $this->assertStringContainsString('bg-slate-900', $dark['sectionClass']);
        $this->assertSame('text-accent', $dark['numColorClass']);
        $this->assertSame('divide-slate-800', $dark['dividerClass']);

        $light = (new MetricsGridViewModel([
            'children' => [['block_key' => 'metric_item', 'block_data' => ['number' => '1']]],
        ], 'es'))->vars();

        $this->assertStringContainsString('bg-white', $light['sectionClass']);
        $this->assertSame('text-primary', $light['numColorClass']);
        $this->assertSame('divide-slate-100', $light['dividerClass']);
    }

    public function testColumnsAreClampedBetween2And4(): void
    {
        $vm = new MetricsGridViewModel([
            'block_config' => ['columns' => 9],
            'children'     => [['block_key' => 'metric_item', 'block_data' => ['number' => '1']]],
        ], 'es');

        $this->assertSame('md:grid-cols-4', $vm->vars()['columnsClass']);
    }
}
