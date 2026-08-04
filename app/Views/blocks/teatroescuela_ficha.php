<?php
/** @var string $title */
/** @var string $activityTypeLabel */
/** @var string $registerLabel */
/** @var string $summary */
/** @var string $status */
/** @var string $statusLabel */
/** @var string $registrationUrl */
/** @var string $videoEmbedUrl */
/** @var list<array<string, mixed>> $instructors */
/** @var string $renderedChildren */
$fields = [
    [lang('Site.detail_field_start_date'), $startDateLabel ?? ''], [lang('Site.detail_field_end_date'), $endDateLabel ?? ''],
    [lang('Site.detail_field_schedule'), $schedule ?? ''], [lang('Site.detail_field_days'), $days ?? ''],
    [lang('Site.detail_field_duration'), $duration ?? ''], [lang('Site.detail_field_venue'), $venue ?? ''],
    [lang('Site.detail_field_capacity'), $capacity ?? ''], [lang('Site.detail_field_price'), $price ?? ''],
    [lang('Site.detail_field_enrollment_fee'), $enrollmentFee ?? ''],
];
?>
<article class="section-sm my-8 min-w-0 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
    <div class="grid min-w-0 gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:p-10">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <?php if ($activityTypeLabel !== ''): ?><span class="rounded-full bg-slate-100 px-3 py-1"><?= esc($activityTypeLabel) ?></span><?php endif; ?>
                <?php if (($category ?? '') !== ''): ?><span class="rounded-full bg-slate-100 px-3 py-1"><?= esc($category) ?></span><?php endif; ?>
                <?php if (($modality ?? '') !== ''): ?><span><?= esc($modality) ?></span><?php endif; ?>
                <?php if ($statusLabel !== ''): ?><span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800"><?= esc($statusLabel) ?></span><?php endif; ?>
            </div>
            <?php if (($title ?? '') !== ''): ?><h2 class="mt-4 break-words text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl"><?= esc($title) ?></h2><?php endif; ?>
            <?php if (($summary ?? '') !== ''): ?><div class="prose prose-slate mt-5 max-w-3xl leading-7"><?= $summary ?></div><?php endif; ?>

            <?php foreach ([['objectives', lang('Site.detail_field_objectives'), $objectives ?? ''], ['requirements', lang('Site.detail_field_requirements'), $requirements ?? ''], ['history', lang('Site.detail_field_history'), $history ?? '']] as $section): ?>
                <?php if ($section[2] !== ''): ?><section class="prose prose-slate mt-8 max-w-none border-t border-slate-100 pt-8"><h3><?= esc($section[1]) ?></h3><?= $section[2] ?></section><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($instructors !== []): ?>
                <section class="mt-8 border-t border-slate-100 pt-8"><h3 class="text-xl font-semibold text-slate-900"><?= esc(lang('Site.theatre_school_instructors_title')) ?></h3><div class="mt-4 flex flex-wrap gap-3"><?php foreach ($instructors as $reference): ?><?= view('blocks/_entry_reference', ['reference' => $reference], ['saveData' => false]) ?><?php endforeach; ?></div></section>
            <?php endif; ?>
        </div>
        <aside class="h-fit min-w-0 rounded-3xl bg-slate-50 p-5 lg:sticky lg:top-6">
            <?php if ($registrationUrl !== '' && $status !== 'finished'): ?><a href="<?= esc($registrationUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary inline-flex min-h-11 w-full justify-center rounded-xl px-4 py-3 text-center font-semibold !text-white break-words"><?= esc($registerLabel) ?></a><?php elseif ($status === 'finished'): ?><p class="rounded-xl bg-slate-200 px-4 py-3 text-center text-sm font-semibold text-slate-600"><?= esc(lang('Site.theatre_school_finished_cta')) ?></p><?php endif; ?>
            <?php if (($contactEmail ?? '') !== ''): ?><a href="mailto:<?= esc($contactEmail) ?>" class="mt-4 block break-words text-sm font-medium text-slate-700 underline underline-offset-4"><?= esc(lang('Site.theatre_school_contact')) ?></a><?php endif; ?>
        </aside>
    </div>
    <dl class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($fields as $field): ?><?php if ($field[1] !== ''): ?><div class="bg-white px-6 py-4 sm:px-8"><dt class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"><?= esc($field[0]) ?></dt><dd class="mt-1 text-sm font-medium text-slate-900"><?= esc($field[1]) ?></dd></div><?php endif; ?><?php endforeach; ?>
    </dl>
    <?php if ($videoEmbedUrl !== ''): ?><section class="border-t border-slate-200 p-6 sm:p-8"><h3 class="text-xl font-semibold text-slate-900"><?= esc(lang('Site.theatre_school_video_title')) ?></h3><div data-video-player data-embed-url="<?= esc($videoEmbedUrl) ?>" data-is-iframe="1" class="relative mt-4 aspect-video overflow-hidden rounded-2xl bg-slate-950"><button type="button" data-poster-overlay class="absolute inset-0 flex items-center justify-center bg-slate-950 text-white transition hover:bg-slate-900" aria-label="<?= esc(lang('Site.theatre_school_video_play')) ?>"><span class="rounded-full bg-white/15 px-5 py-3 text-sm font-semibold"><?= esc(lang('Site.theatre_school_video_play')) ?></span></button></div></section><?php endif; ?>
    <?= $renderedChildren ?? '' ?>
</article>
