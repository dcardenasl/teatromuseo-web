<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class DocumentDownloadViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $document = $this->configMediaReference('document');

        $url = $document['url'];
        $meta = $this->documentTypeFromUrl($url);

        return [
            'title'          => $this->dataString('title'),
            'description'    => $this->dataString('description'),
            'buttonLabel'    => $this->dataString('button_label', 'Descargar'),
            'document'       => $document,
            'documentUrl'    => $url,
            'docType'        => $meta['docType'],
            'ext'            => $meta['ext'],
            'openInNewTab'   => $this->configBool('open_in_new_tab', true),
            'cssClass'       => trim($this->configString('css_class')),
        ];
    }
}
