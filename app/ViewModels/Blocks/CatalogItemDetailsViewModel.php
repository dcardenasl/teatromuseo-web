<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

class CatalogItemDetailsViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $item = $this->context['catalog_item'] ?? null;
        if (!is_array($item)) {
            return [
                'hasItem' => false,
                'fallbackTitle' => $this->dataString('fallback_title', 'Detalles de Obra'),
            ];
        }

        $techniques = $this->normalizeTechniques($item);
        $dimensions = (string) ($item['dimensions'] ?? '');
        $period = (string) ($item['period'] ?? '');
        $location = (string) ($item['ubicacion'] ?? '');
        $curiosity = (string) ($item['curiosidad'] ?? '');

        return [
            'hasItem' => true,
            'techniques' => $techniques,
            'technique' => implode(', ', $techniques),
            'period' => $period,
            'dimensions' => $dimensions,
            'location' => $location,
            'curiosity' => $curiosity,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function normalizeTechniques(array $item): array
    {
        $candidates = $item['techniques'] ?? $item['technique'] ?? [];
        $names = [];

        $appendName = static function (mixed $value) use (&$names): void {
            if (! is_scalar($value)) {
                return;
            }

            $name = trim((string) $value);
            if ($name !== '') {
                $names[] = $name;
            }
        };

        if (is_string($candidates) || is_numeric($candidates)) {
            $appendName($candidates);
        } elseif (is_array($candidates)) {
            if ($candidates !== [] && ! array_is_list($candidates)) {
                $appendName($candidates['name'] ?? $candidates['title'] ?? $candidates['localized']['name'] ?? null);
            } else {
                foreach ($candidates as $candidate) {
                    if (is_array($candidate)) {
                        $appendName($candidate['name'] ?? $candidate['title'] ?? $candidate['localized']['name'] ?? null);
                        continue;
                    }

                    $appendName($candidate);
                }
            }
        }

        $names = array_values(array_unique(array_filter($names, static fn (string $value): bool => $value !== '')));

        return $names;
    }
}
