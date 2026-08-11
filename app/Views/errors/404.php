<div class="container mx-auto px-4 py-20 text-center">
    <h1 class="text-5xl font-bold text-gray-800 mb-4">404</h1>
    <p class="text-2xl text-gray-600 mb-8"><?= esc(lang('Site.error_404_title')) ?></p>
    <p class="text-gray-500 mb-8"><?= esc($message ?? lang('Site.error_404_message')) ?></p>
    <a href="<?= lang_url(\App\Support\PublicPaths::homepagePath(service('request')->getLocale())) ?>" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <?= esc(lang('Site.error_404_back_home')) ?>
    </a>
</div>
