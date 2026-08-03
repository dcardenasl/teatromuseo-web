<?php
/** @var array<string, mixed> $config */
/** @var string $renderedChildren */
$cssClass = $config['css_class'] ?? '';
$layout   = $config['layout'] ?? 'block';

// Map CMS layout to Tailwind CSS classes
$layoutClass = match ($layout) {
    'grid-2'   => 'grid grid-cols-1 md:grid-cols-2 gap-8 items-start',
    'grid-3'   => 'grid grid-cols-1 md:grid-cols-3 gap-6 items-start',
    'flex-row' => 'flex flex-col md:flex-row gap-6 items-start',
    default    => 'space-y-6',
};

// If a custom class doesn't override container mx-auto, apply container layout defaults
if ($cssClass === '') {
    $cssClass = 'container mx-auto';
}
?>
<div class="<?= esc($cssClass) ?> <?= esc($layoutClass) ?>">
    <?= $renderedChildren ?>
</div>
