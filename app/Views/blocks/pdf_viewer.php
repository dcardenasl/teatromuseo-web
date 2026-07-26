<?php
/**
 * pdf_viewer block — all variables prepared by PdfViewerViewModel
 * (registered in BlockRenderer::VIEW_MODELS).
 *
 * @var string $heading
 * @var array{source_kind: string, file_id: int|null, url: string} $pdfFile
 * @var string $pdfUrl
 * @var string $height
 * @var bool   $allowDownload
 * @var string $cssClass
 */

if ($pdfUrl === '') {
    return;
}
?>

<section class="section-sm <?= esc($cssClass) ?>">
    <div class="max-w-5xl mx-auto px-4">
        <?php if ($heading !== ''): ?>
            <h3 class="text-xl font-bold text-slate-800 mb-4 tracking-tight">
                <?= esc($heading) ?>
            </h3>
        <?php endif; ?>

        <div class="relative w-full overflow-hidden border border-slate-200/80 rounded-2xl bg-slate-100 shadow-sm" style="height: <?= esc($height) ?>;">
            <object data="<?= esc($pdfUrl) ?>" type="application/pdf" class="w-full h-full">
                <div class="flex flex-col items-center justify-center h-full p-8 text-center bg-slate-50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-slate-400 mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 0 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z" />
                    </svg>
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Previsualización no disponible</h4>
                    <p class="text-xs text-slate-500 max-w-sm mb-4">Tu navegador o dispositivo no soporta la visualización directa de PDFs. Puedes abrir el archivo en una ventana nueva o descargarlo.</p>
                    <a href="<?= esc($pdfUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-sm transition-all">
                        <span>Ver PDF en pantalla completa</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                        </svg>
                    </a>
                </div>
            </object>
        </div>

        <?php if ($allowDownload): ?>
            <div class="mt-4 flex justify-end">
                <a href="<?= esc($pdfUrl) ?>" download class="inline-flex items-center gap-2 text-xs font-semibold text-violet-600 hover:text-violet-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Descargar archivo PDF</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
