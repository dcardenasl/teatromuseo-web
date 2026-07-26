<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class DocumentGalleryViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $rawDocs = $this->data()['documents'] ?? [];
        $rawDocs = is_array($rawDocs) ? $rawDocs : [];

        $documents = [];
        foreach ($rawDocs as $d) {
            $doc = is_array($d) ? $d : [];
            $file = $this->mediaReferenceFromPayload($doc, 'file');
            $fileUrl = $file['url'];
            $title   = is_scalar($doc['title'] ?? null) ? (string) $doc['title'] : '';
            $desc    = is_scalar($doc['description'] ?? null) ? (string) $doc['description'] : '';
            $meta = $this->documentTypeFromUrl($fileUrl);

            $documents[] = [
                'fileUrl'     => $fileUrl,
                'title'       => $title,
                'description' => $desc,
                'docType'     => $meta['docType'],
                'ext'         => $meta['ext'],
            ];
        }

        return [
            'title'          => $this->dataString('title'),
            'description'    => $this->dataString('description'),
            'documents'      => $documents,
            'layout'         => $this->configString('layout', 'grid_cards'),
            'showFileMeta'   => $this->configBool('show_file_meta', true),
            'openInNewTab'   => $this->configBool('open_in_new_tab', true),
            'cssClass'       => trim($this->configString('css_class')),
        ];
    }
}
