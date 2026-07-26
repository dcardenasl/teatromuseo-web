<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class MetricsGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $stats   = $this->stats();
        $variant = $this->configString('variant', 'light');
        $columns = min(4, max(2, $this->configInt('columns', count($stats))));

        [$sectionClass, $numColorClass, $lblColorClass, $iconColorClass, $dividerClass] = $this->variantClasses($variant);

        return [
            'stats'          => $stats,
            'cssClass'       => trim($this->configString('css_class')),
            'columnsClass'   => match ($columns) {
                2       => 'md:grid-cols-2',
                3       => 'md:grid-cols-3',
                default => 'md:grid-cols-4',
            },
            'sectionClass'   => $sectionClass,
            'numColorClass'  => $numColorClass,
            'lblColorClass'  => $lblColorClass,
            'iconColorClass' => $iconColorClass,
            'dividerClass'   => $dividerClass,
        ];
    }

    /**
     * @return list<array{prefix: string, number: string, suffix: string, label: string, description: string, source_label: string, source_url: string, icon: string, num_only: int, display_suffix: string, display_value: string}>
     */
    public function stats(): array
    {
        $stats = [];

        foreach ($this->children() as $child) {
            if (($child['block_key'] ?? '') !== 'metric_item') {
                continue;
            }

            $childData = is_array($child['block_data'] ?? null) ? $child['block_data'] : [];
            $string    = static fn (string $key): string => is_scalar($childData[$key] ?? null) ? (string) $childData[$key] : '';

            $prefix = $string('prefix');
            $number = $string('number');
            $suffix = $string('suffix');

            $stats[] = [
                'prefix'         => $prefix,
                'number'         => $number,
                'suffix'         => $suffix,
                'label'          => $string('label'),
                'description'    => $string('description'),
                'source_label'   => $string('source_label'),
                'source_url'     => lang_url($string('source_url'), $this->lang),
                'icon'           => $string('icon'),
                'num_only'       => (int) preg_replace('/[^0-9]/', '', $number),
                'display_suffix' => $suffix !== '' ? $suffix : (string) preg_replace('/[0-9]/', '', $number),
                'display_value'  => $prefix . $number . $suffix,
            ];
        }

        return $stats;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string} [section, number color, label color, icon color, divider color]
     */
    private function variantClasses(string $variant): array
    {
        $base = 'rounded-2xl py-10 px-6 md:px-12 ';

        return match ($variant) {
            'dark' => [
                $base . 'bg-slate-900 border border-slate-800 text-white shadow-md',
                'text-accent',
                'text-slate-400',
                'text-accent bg-slate-800',
                'divide-slate-800',
            ],
            'primary' => [
                $base . 'bg-gradient-to-tr from-primary to-accent text-white shadow-md',
                'text-white',
                'text-sky-100/90',
                'text-white bg-white/15',
                'divide-white/10',
            ],
            default => [
                $base . 'bg-white border border-slate-100/80 shadow-sm',
                'text-primary',
                'text-text-secondary',
                'text-primary bg-sky-50',
                'divide-slate-100',
            ],
        };
    }
}
