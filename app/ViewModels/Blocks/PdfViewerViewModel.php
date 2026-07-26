<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class PdfViewerViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $pdfFile = $this->configMediaReference('pdf_file');

        return [
            'heading'         => $this->dataString('heading'),
            'pdfFile'         => $pdfFile,
            'pdfUrl'          => $pdfFile['url'],
            'height'          => $this->configString('height', '600px'),
            'allowDownload'   => $this->configBool('allow_download', true),
            'cssClass'        => trim($this->configString('css_class')),
        ];
    }
}
