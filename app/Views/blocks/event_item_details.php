<?php
/**
 * Bloque: Detalles Dinámicos de Evento (Ficha Técnica)
 * Usa EventItemDetailsViewModel
 */

if (!($hasEvent ?? false)):
    $fallbackTitle = $fallbackTitle ?? 'Detalles de Evento';
?>
<div class="p-8 bg-blue-50 text-center border border-dashed border-blue-300">
    <h2 class="text-2xl font-bold text-blue-400"><?= esc($fallbackTitle) ?> (Previsualización)</h2>
    <p class="text-blue-500">Este bloque mostrará la fecha, el lugar, la capacidad y el estado del evento.</p>
</div>
<?php else: ?>
<section class="section pt-0">
    <div class="container-narrow">
        <h3 class="text-xl font-bold text-slate-900 mb-6 uppercase tracking-wider text-sm border-b border-slate-100 pb-3"><?= esc(lang('Site.event_technical_details_title')) ?></h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
            <?php if (($startTime ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.event_schedule_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary">
                    <time datetime="<?= esc($startTimeIso ?? '') ?>"><?= esc($startTime) ?></time>
                    <?php if (($endTime ?? '') !== ''): ?>
                        <span aria-hidden="true"> - </span>
                        <time datetime="<?= esc($endTimeIso ?? '') ?>"><?= esc($endTime) ?></time>
                    <?php endif; ?>
                </dd>
            </div>
            <?php endif; ?>
            
            <?php if (($venue ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.event_venue_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($venue) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($capacity ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.event_capacity_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($capacity) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($availableSpots ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.event_available_spots_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($availableSpots) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (($status ?? '') !== ''): ?>
            <div class="sm:col-span-1">
                <dt class="text-sm font-medium text-text-muted"><?= esc(lang('Site.event_status_label')) ?></dt>
                <dd class="mt-1 text-base text-text-primary"><?= esc($status) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</section>
<?php endif; ?>
