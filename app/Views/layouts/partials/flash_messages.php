<?php $session = \App\Support\PublicSession::current(); ?>

<?php if ($session?->has('success')): ?>
    <div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        <?= esc($session->getFlashdata('success')) ?>
    </div>
<?php endif; ?>

<?php if ($session?->has('error')): ?>
    <div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?= esc($session->getFlashdata('error')) ?>
    </div>
<?php endif; ?>

<?php if ($session?->has('warning')): ?>
    <div class="fixed top-4 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
        <?= esc($session->getFlashdata('warning')) ?>
    </div>
<?php endif; ?>
