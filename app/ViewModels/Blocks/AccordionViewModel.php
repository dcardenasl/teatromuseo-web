<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

/**
 * Accordion block view model.
 *
 * Transforms accordion_item children into a flat list of items with
 * sanitized properties: title, content, is_open flag.
 */
class AccordionViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        return [
            'items'    => $this->items(),
            'cssClass' => trim($this->configString('css_class')),
        ];
    }

    /**
     * Extract accordion items from children.
     *
     * @return list<array{title: string, content: string, is_open: bool}>
     */
    private function items(): array
    {
        $items = [];

        foreach ($this->children() as $child) {
            if (($child['block_key'] ?? '') !== 'accordion_item') {
                continue;
            }

            $childData   = is_array($child['block_data'] ?? null) ? $child['block_data'] : [];
            $childConfig = is_array($child['block_config'] ?? null) ? $child['block_config'] : [];

            $title   = is_scalar($childData['title'] ?? null) ? (string) $childData['title'] : '';
            $content = block_text_content($childData, '');
            $isOpen  = filter_var($childConfig['is_open'] ?? false, FILTER_VALIDATE_BOOL);

            if ($title !== '') {
                $items[] = [
                    'title'   => $title,
                    'content' => $content,
                    'is_open' => $isOpen,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function children(): array
    {
        return is_array($this->block['children'] ?? null) ? $this->block['children'] : [];
    }
}
