<?php
/** @var array<string, mixed> $block */
/** @var array<string, mixed> $config */
/** @var array<string, mixed> $data */

$title = (string) ($data['title'] ?? '');
$message = (string) ($data['message'] ?? '');
$type = (string) ($config['type'] ?? 'info');
$dismissible = filter_var($config['dismissible'] ?? true, FILTER_VALIDATE_BOOL);
$cssClass = trim((string) ($config['css_class'] ?? ''));

if ($message === '') {
    return;
}

// Map alert types to colors
$typeStyles = [
    'info' => [
        'bg' => 'bg-blue-50/90 border-blue-200/80',
        'text' => 'text-blue-800',
        'iconColor' => 'text-blue-500',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>'
    ],
    'success' => [
        'bg' => 'bg-emerald-50/90 border-emerald-200/80',
        'text' => 'text-emerald-800',
        'iconColor' => 'text-emerald-500',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>'
    ],
    'warning' => [
        'bg' => 'bg-amber-50/90 border-amber-200/80',
        'text' => 'text-amber-800',
        'iconColor' => 'text-amber-500',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>'
    ],
    'danger' => [
        'bg' => 'bg-rose-50/90 border-rose-200/80',
        'text' => 'text-rose-800',
        'iconColor' => 'text-rose-500',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>'
    ]
];

$style = $typeStyles[$type] ?? $typeStyles['info'];
?>

<div 
    x-data="{ open: true }" 
    x-show="open" 
    x-transition:leave="transition ease-in duration-300 transform"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    class="max-w-4xl mx-auto my-4 p-4 rounded-xl border flex gap-3 items-start <?= $style['bg'] ?> <?= $style['text'] ?> <?= esc($cssClass) ?>"
>
    <!-- Icon -->
    <div class="shrink-0 mt-0.5 <?= $style['iconColor'] ?>">
        <?= $style['icon'] ?>
    </div>

    <!-- Content -->
    <div class="flex-1 min-w-0">
        <?php if ($title !== ''): ?>
            <h4 class="font-bold text-sm leading-tight mb-1"><?= esc($title) ?></h4>
        <?php endif; ?>
        <div class="text-sm leading-relaxed opacity-95">
            <?= block_text_content(['content' => $message], '') ?>
        </div>
    </div>

    <!-- Dismiss Button -->
    <?php if ($dismissible): ?>
        <button 
            @click="open = false" 
            type="button" 
            class="shrink-0 rounded-lg p-1.5 inline-flex h-8 w-8 hover:bg-black/5 transition-colors focus:outline-none focus:ring-2 focus:ring-black/10" 
            aria-label="Cerrar"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l1.293 1.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    <?php endif; ?>
</div>
