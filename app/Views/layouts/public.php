<!--
    Each view() call below passes ['saveData' => false]. CI4's Config\View::$saveData
    defaults to true, which persists a view's variables into the shared view store for
    the rest of the request — so without this flag, e.g. $view's own data (title,
    categories, ...) would leak into the footer/flash partials rendered afterwards, or
    vice versa. Keep this on every call here, and on any new one added to this file.
-->
<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <?= view('layouts/partials/head', $data ?? [], ['saveData' => false]) ?>
</head>
<body>
    <?= view('layouts/partials/header', ['menu' => $mainMenu ?? [], 'settings' => $settings ?? [], 'localized_urls' => $localized_urls ?? []], ['saveData' => false]) ?>

    <main>
        <?= view($view, $data ?? [], ['saveData' => false]) ?>
    </main>

    <?= view('layouts/partials/footer', ['menu' => $footerMenu ?? [], 'legalMenu' => $legalMenu ?? [], 'settings' => $settings ?? []], ['saveData' => false]) ?>
    <?= view('layouts/partials/flash_messages', [], ['saveData' => false]) ?>
</body>
</html>
