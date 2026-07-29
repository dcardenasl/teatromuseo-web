<?php
/**
 * Bloque: Cabecera Dinámica de Evento
 * Muestra el título, fechas, e imagen principal del evento extraído del contexto.
 */

$event = $context['event_item'] ?? null;
if (!$event):
?>
<div class="p-8 bg-blue-50 text-center border border-dashed border-blue-300">
    <h2 class="text-2xl font-bold text-blue-400">Cabecera de Evento (Previsualización)</h2>
    <p class="text-blue-500">Este bloque mostrará el título, fechas y portada del evento.</p>
</div>
<?php else: 
    $title = $event['title'] ?? $event['name'] ?? 'Evento sin título';
    $summary = $event['localized']['description'] ?? $event['description'] ?? '';
    $image = $event['cover_image'] ?? $event['featured_image'] ?? null;
    $imageUrl = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
    $startTime = (string) ($event['start_time'] ?? '');
    $endTime = (string) ($event['end_time'] ?? '');
    $venue = (string) ($event['venue'] ?? '');
    $eventType = (string) ($event['event_type'] ?? '');
    $eventTypeLabel = match ($eventType) {
        'function' => lang('Site.event_type_function'),
        'festival' => lang('Site.event_type_festival'),
        'course' => lang('Site.event_type_course'),
        'workshop' => lang('Site.event_type_workshop'),
        default => lang('Site.event_type_other'),
    };
?>
<header class="relative w-full h-[60vh] min-h-[500px] flex items-center justify-center bg-gray-900 mb-12">
    <?php if ($imageUrl !== ''): ?>
        <img src="<?= esc($imageUrl) ?>" alt="<?= esc($title) ?>" class="absolute inset-0 w-full h-full object-cover opacity-50 mix-blend-multiply">
    <?php endif; ?>
    
    <div class="relative z-10 p-8 text-center text-white max-w-5xl">
        <!-- Event tags/categories could go here -->
        <h1 class="text-5xl md:text-7xl font-black tracking-tight mb-6 uppercase"><?= esc($title) ?></h1>
        <?php if ($summary !== ''): ?>
            <p class="text-xl md:text-2xl font-light text-gray-200 mb-8 max-w-3xl mx-auto"><?= esc($summary) ?></p>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <?php if ($eventTypeLabel !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-white/90">
                    <?= esc($eventTypeLabel) ?>
                </span>
            <?php endif; ?>
            <?php if ($startTime !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold text-white/90">
                    Inicio: <?= esc(date('d M Y · H:i', strtotime($startTime))) ?>
                </span>
            <?php endif; ?>
            <?php if ($endTime !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold text-white/90">
                    Fin: <?= esc(date('d M Y · H:i', strtotime($endTime))) ?>
                </span>
            <?php endif; ?>
            <?php if ($venue !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold text-white/90">
                    <?= esc($venue) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php endif; ?>
