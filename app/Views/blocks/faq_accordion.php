<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$title = esc($data['title'] ?? '');
$description = esc($data['description'] ?? '');
$faqs = $data['faqs'] ?? [];
$faqs = is_array($faqs) ? $faqs : [];
$cssClass = esc(trim($config['css_class'] ?? ''));

$faqId = uniqid('faq_');

// Prepare JSON-LD Schema
$jsonSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [],
];

foreach ($faqs as $faq) {
    $q = trim((string) ($faq['question'] ?? ''));
    $a = trim((string) ($faq['answer'] ?? ''));
    if ($q !== '' && $a !== '') {
        $jsonSchema['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags($a, '<h1><h2><h3><h4><h5><h6><p><br><b><strong><i><em><ul><ol><li><a>'),
            ],
        ];
    }
}
?>

<section id="faq" class="section scroll-mt-16 <?= esc($cssClass) ?>" data-faq-id="<?= $faqId ?>">
    <div class="max-w-4xl mx-auto px-4">
        <?php if ($title !== ''): ?>
            <div class="text-center max-w-2xl mx-auto mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight mb-3 bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                    <?= $title ?>
                </h2>
                <?php if ($description !== ''): ?>
                    <p class="text-sm text-slate-500">
                        <?= $description ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($faqs === []): ?>
            <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-400">
                No hay preguntas frecuentes registradas.
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($faqs as $idx => $faq): 
                    $question = esc($faq['question'] ?? '');
                    $answer = $faq['answer'] ?? ''; // rich text
                    if ($question === '' || $answer === '') continue;
                ?>
                    <div class="faq-item border border-slate-200 bg-white rounded-2xl overflow-hidden hover:border-slate-300 transition-colors duration-300">
                        <button type="button" 
                                data-faq-toggle
                                class="w-full flex items-center justify-between gap-4 p-5 text-left font-semibold text-slate-800 hover:text-violet-600 transition-colors focus:outline-none"
                                aria-expanded="false">
                            <span class="text-base leading-snug"><?= $question ?></span>
                            <span class="shrink-0 flex items-center justify-center w-6 h-6 rounded-lg bg-slate-50 text-slate-400 group-hover:bg-violet-50 group-hover:text-violet-600 transition-colors transform duration-300" data-faq-icon>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </span>
                        </button>
                        <div data-faq-content class="hidden border-t border-slate-100 bg-slate-50/30">
                            <div class="prose prose-slate prose-sm max-w-none p-5 text-slate-600 leading-relaxed">
                                <?= $answer ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($jsonSchema['mainEntity'] !== []): ?>
        <!-- JSON-LD SEO Structured Data for Google -->
        <script type="application/ld+json">
            <?= json_encode($jsonSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
        </script>
    <?php endif; ?>
</section>

<script <?= csp_script_nonce() ?>>
(function() {
    const root = document.querySelector('[data-faq-id="<?= $faqId ?>"]');
    if (!root) return;

    const items = root.querySelectorAll('.faq-item');
    items.forEach(item => {
        const toggle = item.querySelector('[data-faq-toggle]');
        const content = item.querySelector('[data-faq-content]');
        const icon = item.querySelector('[data-faq-icon]');
        if (!toggle || !content) return;

        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            
            // Close all other items (accordion behavior)
            items.forEach(otherItem => {
                if (otherItem !== item) {
                    const otherToggle = otherItem.querySelector('[data-faq-toggle]');
                    const otherContent = otherItem.querySelector('[data-faq-content]');
                    const otherIcon = otherItem.querySelector('[data-faq-icon]');
                    if (otherToggle && otherContent) {
                        otherToggle.setAttribute('aria-expanded', 'false');
                        otherContent.classList.add('hidden');
                        if (otherIcon) {
                            otherIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>`;
                            otherIcon.classList.remove('bg-violet-50', 'text-violet-600', 'rotate-45');
                        }
                    }
                }
            });

            // Toggle active item
            if (isExpanded) {
                toggle.setAttribute('aria-expanded', 'false');
                content.classList.add('hidden');
                if (icon) {
                    icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>`;
                    icon.classList.remove('bg-violet-50', 'text-violet-600', 'rotate-45');
                }
            } else {
                toggle.setAttribute('aria-expanded', 'true');
                content.classList.remove('hidden');
                if (icon) {
                    icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>`;
                    icon.classList.add('bg-violet-50', 'text-violet-600', 'rotate-45');
                }
            }
        });
    });
})();
</script>
