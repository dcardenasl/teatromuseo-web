<?php
/** @var array<string, mixed> $data */
$reference = is_array($data['entry'] ?? null) ? $data : null;
if ($reference !== null): ?>
    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
        <?= view('blocks/_entry_reference', ['reference' => $reference], ['saveData' => false]) ?>
    </div>
<?php endif; ?>
