<?php
/**
 * Bloque: Contenido Dinámico de Evento
 * Usa EventItemContentViewModel
 */

if (!($hasEvent ?? false)):
    $fallbackTitle = $fallbackTitle ?? lang('Site.event_content_preview_title');
?>
<div class="p-8 bg-blue-50 text-center border border-dashed border-blue-300">
    <h2 class="text-2xl font-bold text-blue-400"><?= esc($fallbackTitle) ?> (<?= esc(lang('Site.preview_label')) ?>)</h2>
    <p class="text-blue-500">Este bloque mostrará la descripción completa o sinopsis del evento.</p>
</div>
<?php else: ?>
    <?php if (($content ?? '') !== ''): ?>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="prose prose-lg prose-gray max-w-none prose-headings:font-bold prose-a:text-primary hover:prose-a:text-primary-light">
            <?= $content ?>
        </div>
    </section>
    <?php endif; ?>
<?php endif; ?>
