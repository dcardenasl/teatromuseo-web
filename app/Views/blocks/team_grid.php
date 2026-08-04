<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 * @var list<array<string, mixed>> $members
*/

$title = esc($title ?? $data['title'] ?? '');
$description = esc($description ?? $data['description'] ?? '');
$columns = esc($columns ?? $config['columns'] ?? '3');
$cssClass = esc(trim($cssClass ?? $config['css_class'] ?? ''));
$members = is_array($members ?? null) ? $members : [];

$colClasses = [
    '2' => 'grid-cols-1 sm:grid-cols-2',
    '3' => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
];
$colClass = $colClasses[$columns] ?? $colClasses['3'];
?>

<section id="team" class="section py-14 sm:py-20 scroll-mt-16 <?= $cssClass ?>">
    <div class="max-w-6xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                    <?= $title ?>
                </h2>
                <div class="mx-auto h-0.5 w-10 bg-slate-900/80" aria-hidden="true"></div>
                <?php if ($description !== ''): ?>
                    <p class="mt-4 text-base text-slate-600">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($members === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay integrantes del equipo registrados.
            </div>
        <?php else: ?>
            <div class="grid gap-x-10 gap-y-14 <?= $colClass ?>">
                <?php foreach ($members as $member):
                    $name = (string) ($member['title'] ?? '');
                    $position = (string) ($member['position'] ?? '');
                    $roles = is_array($member['roles'] ?? null) ? $member['roles'] : ($position !== '' ? [$position] : []);
                    $email = (string) ($member['email'] ?? '');
                    $url = (string) ($member['url'] ?? '');
                    $image = is_array($member['image'] ?? null) ? $member['image'] : [];
                    $hoverImage = is_array($member['hover_image'] ?? null) ? $member['hover_image'] : $image;
                    if ($name === '') continue;
                ?>
                    <article class="group">
                        <?php if ($url !== ''): ?><a href="<?= esc($url) ?>" class="block"><?php endif; ?>
                            <div class="relative aspect-square overflow-hidden rounded-full transition-transform duration-500 group-hover:scale-[1.02]">
                                <?php if (($image['url'] ?? '') !== ''): ?>
                                    <?= view('components/responsive-image', [
                                        'src' => $image['url'],
                                        'alt' => $name,
                                        'class' => 'absolute inset-0 h-full w-full object-cover transition-opacity duration-500 group-hover:opacity-0',
                                        'variants' => $image['variants'] ?? null,
                                    ], ['saveData' => false]) ?>
                                <?php endif; ?>
                                <?php if (($hoverImage['url'] ?? '') !== ''): ?>
                                    <?= view('components/responsive-image', [
                                        'src' => $hoverImage['url'],
                                        'alt' => $name,
                                        'class' => 'absolute inset-0 h-full w-full object-cover opacity-0 transition-[opacity,transform,filter] duration-500 group-hover:opacity-100 group-hover:scale-105',
                                        'variants' => $hoverImage['variants'] ?? null,
                                        'hideOnError' => true,
                                    ], ['saveData' => false]) ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-12 min-h-[146px] bg-white px-5 py-7 text-center">
                                <h3 class="text-lg font-bold uppercase tracking-wide text-slate-700 group-hover:text-primary transition-colors"><?= esc($name) ?></h3>
                                <?php if ($roles !== []): ?>
                                    <div class="mt-4 space-y-1 font-serif text-base italic text-slate-700">
                                        <?php foreach ($roles as $role): ?>
                                            <p><?= esc((string) $role) ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($email !== ''): ?><a href="mailto:<?= esc($email) ?>" class="mt-3 inline-block font-serif text-base italic text-orange-500 hover:text-orange-600 transition-colors"><?= esc($email) ?></a><?php endif; ?>
                            </div>
                        <?php if ($url !== ''): ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
