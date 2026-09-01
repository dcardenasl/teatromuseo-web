<?php
/**
 * @var string $name
 * @var array{source_kind: string, file_id: int|null, url: string} $photo
 * @var string $position
 * @var string $bio
 * @var string $linkedin
 */

if (!empty($name)):
?>
    <div class="flex flex-col items-center text-center p-6 rounded-3xl border border-slate-200/50 bg-white hover:border-violet-200 hover:shadow-md transition-all duration-300 group">
        <!-- Photo Wrapper -->
        <div class="relative w-28 h-28 rounded-full overflow-hidden bg-slate-50 border border-slate-200 mb-5 group-hover:scale-105 transition-transform duration-300">
            <?php if (!empty($photo['url'])): ?>
                <?= view('components/responsive-image', [
                    'src'      => $photo['url'],
                    'alt'      => $name,
                    'class'    => 'w-full h-full object-cover',
                    'variants' => $photo['variants'] ?? null,
                    'preferredVariant' => 'thumb',
                    'sizes'    => '7rem',
                    'maxVariantWidth' => 160,
                ], ['saveData' => false]) ?>
            <?php else: ?>
                <!-- Initials fallback -->
                <div class="flex items-center justify-center w-full h-full bg-gradient-to-br from-violet-100 to-violet-50 text-violet-600 font-extrabold text-2xl">
                    <?= esc(strtoupper(substr($name, 0, 1))) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="min-w-0 flex-1 flex flex-col justify-between w-full">
            <div>
                <h3 class="text-lg font-bold text-slate-800 tracking-tight group-hover:text-violet-600 transition-colors truncate">
                    <?= esc($name) ?>
                </h3>
                <p class="text-xs font-semibold text-violet-600 uppercase tracking-wider mt-1 mb-3">
                    <?= esc($position) ?>
                </p>
                <?php if (!empty($bio)): ?>
                    <p class="text-sm text-slate-500 line-clamp-3 leading-relaxed mb-4">
                        <?= esc($bio) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Social -->
            <?php if (!empty($linkedin) && $linkedin !== '#'): ?>
                <div class="flex justify-center pt-2 border-t border-slate-100 w-full">
                    <a href="<?= esc($linkedin) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer" 
                       class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-50 hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
