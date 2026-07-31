<?php
/**
 * Bloque: Cabecera Dinámica de Evento
 * Usa EventItemHeaderViewModel
 */

if (!($hasEvent ?? false)):
    $fallbackTitle = $fallbackTitle ?? 'Cabecera de Evento';
?>
<div class="p-8 bg-slate-50 text-center border border-dashed border-slate-300">
    <h2 class="text-xl font-bold text-slate-500"><?= esc($fallbackTitle) ?> (Previsualización)</h2>
</div>
<?php else: ?>
<!-- ── Breadcrumb ─────────────────────────────────────────────────── -->
<div class="bg-white border-b border-slate-100">
    <div class="container-narrow py-3">
        <nav class="flex items-center gap-2 text-sm text-text-muted" aria-label="Breadcrumb">
            <a href="<?= lang_url('/') ?>" class="hover:text-primary transition-colors">
                <?= esc($homeLabel ?? 'Inicio') ?>
            </a>
            <span aria-hidden="true">/</span>
            <a href="<?= esc($breadcrumbUrl ?? '') ?>" class="hover:text-primary transition-colors">
                <?= esc($breadcrumbLabel ?? '') ?>
            </a>
            <span aria-hidden="true">/</span>
            <span class="text-text-primary line-clamp-1 max-w-xs" aria-current="page">
                <?= esc($title ?? '') ?>
            </span>
        </nav>
    </div>
</div>

<article class="section bg-background pt-12 pb-0">
    <div class="container-narrow">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex flex-wrap gap-1.5 mb-4">
                <?php if (($eventTypeLabel ?? '') !== ''): ?>
                    <span class="badge badge-secondary uppercase tracking-widest text-xs"><?= esc($eventTypeLabel) ?></span>
                <?php endif; ?>
            </div>

            <h1 class="section-title text-3xl sm:text-4xl leading-tight mb-4">
                <?= esc($title ?? '') ?>
            </h1>

            <?php if (($summary ?? '') !== ''): ?>
                <p class="text-lg text-text-secondary leading-relaxed mb-6">
                    <?= esc($summary) ?>
                </p>
            <?php endif; ?>

            <div class="flex items-center gap-4 text-sm text-text-muted">
                <?php if (($startTime ?? '') !== ''): ?>
                    <time datetime="<?= esc($startTimeIso ?? '') ?>">
                        <?= esc(lang('Site.event_starts_label')) ?>: <?= esc($startTime) ?>
                    </time>
                <?php endif; ?>
                <?php if (($endTime ?? '') !== '' && ($endTime !== $startTime)): ?>
                    <span class="mx-1">&mdash;</span>
                    <time datetime="<?= esc($endTimeIso ?? '') ?>">
                        <?= esc(lang('Site.event_ends_label')) ?>: <?= esc($endTime) ?>
                    </time>
                <?php endif; ?>
            </div>
        </header>

        <!-- Featured image -->
        <?php if (($imageUrl ?? '') !== ''): ?>
            <figure class="mb-12 -mx-4 sm:mx-0 overflow-hidden sm:rounded-xl">
                <?php if (str_starts_with($imageUrl, 'http')): ?>
                    <img src="<?= esc($imageUrl) ?>" alt="<?= esc($title ?? '') ?>" class="w-full aspect-video object-cover" loading="eager" fetchpriority="high">
                <?php else: ?>
                    <?= view('components/responsive-image', [
                        'src'           => $imageUrl,
                        'alt'           => $title ?? '',
                        'class'         => 'w-full aspect-video object-cover shadow-sm',
                        'variants'      => null,
                        'loading'       => 'eager',
                        'fetchPriority' => 'high',
                    ], ['saveData' => false]) ?>
                <?php endif; ?>
            </figure>
        <?php endif; ?>
    </div>
</article>
<?php endif; ?>
