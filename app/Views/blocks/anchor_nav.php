<?php
/**
 * @var array $block
 * @var array $config
 * @var array $data
 */

$anchors = $data['anchors'] ?? [];
$anchors = is_array($anchors) ? $anchors : [];
$cssClass = esc(trim($config['css_class'] ?? ''));
$navId = uniqid('anchor_nav_');

if ($anchors === []) {
    return;
}
?>

<div id="<?= $navId ?>" class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur-md transition-all duration-300 <?= $cssClass ?>">
    <div class="max-w-5xl mx-auto px-4">
        <nav class="flex items-center gap-1 overflow-x-auto py-3 no-scrollbar scroll-smooth">
            <?php foreach ($anchors as $idx => $anchor): 
                $label = esc($anchor['label'] ?? '');
                $targetId = esc(ltrim($anchor['anchor_id'] ?? '', '#'));
                if ($label === '' || $targetId === '') continue;
            ?>
                <a href="#<?= $targetId ?>" 
                   data-anchor-target="<?= $targetId ?>"
                   class="shrink-0 px-4 py-1.5 rounded-full text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all duration-300 <?= $idx === 0 ? 'bg-violet-50 text-violet-600 active-anchor' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<script <?= csp_script_nonce() ?>>
(function() {
    const root = document.getElementById('<?= $navId ?>');
    if (!root) return;

    const links = root.querySelectorAll('[data-anchor-target]');
    if (links.length === 0) return;

    const targets = [];
    links.forEach(link => {
        const id = link.getAttribute('data-anchor-target');
        const element = document.getElementById(id);
        if (element) {
            targets.push({ link, element });
        }
    });

    // Smooth Scroll Click Binding
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const id = link.getAttribute('data-anchor-target');
            const target = document.getElementById(id);
            if (target) {
                const headerOffset = root.offsetHeight + 10;
                const elementPosition = target.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.scrollY - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                
                // Active class update
                links.forEach(l => l.classList.remove('bg-violet-50', 'text-violet-600', 'active-anchor'));
                link.classList.add('bg-violet-50', 'text-violet-600', 'active-anchor');
            }
        });
    });

    // Scroll Tracking with IntersectionObserver
    const observerOptions = {
        root: null,
        rootMargin: '-20% 0px -60% 0px', // Trigger when section is in the middle of screen
        threshold: 0
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                const matchingLink = root.querySelector(`[data-anchor-target="${id}"]`);
                if (matchingLink) {
                    links.forEach(l => l.classList.remove('bg-violet-50', 'text-violet-600', 'active-anchor'));
                    matchingLink.classList.add('bg-violet-50', 'text-violet-600', 'active-anchor');
                    
                    // Auto scroll the horizontal nav bar to keep active item in view
                    matchingLink.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
                }
            }
        });
    }, observerOptions);

    targets.forEach(t => observer.observe(t.element));
})();
</script>

<style <?= csp_style_nonce() ?>>
/* Hide default scrollbar on horizontal nav scroll list */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>
