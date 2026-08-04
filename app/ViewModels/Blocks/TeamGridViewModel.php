<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

use App\Services\SiteEntryService;

final class TeamGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $collectionKey = $this->configString('source_collection');
        $limit = max(1, min(30, $this->configInt('items_limit', 15)));
        $filterNames = array_values(array_filter(array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            explode(',', $this->configString('filter_names'))
        )));
        $requestedOrder = array_flip($filterNames);
        $members = [];
        $seenNames = [];

        $service = $this->contextService('siteEntryService', SiteEntryService::class);
        if ($service !== null && $collectionKey !== '') {
            try {
                $result = $service->list($this->lang, $collectionKey, [
                    // The roster filter may target entries with a high
                    // editorial sort order; fetch the full small collection
                    // before applying it.
                    'per_page' => 100,
                    'order_by' => 'sort_order',
                    'order_direction' => 'asc',
                    'include' => 'listing_content',
                ]);
                foreach ((array) ($result['data'] ?? []) as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $primary = is_array($entry['featured_image'] ?? null)
                        ? $entry['featured_image']
                        : (is_array($entry['cover_image'] ?? null)
                            ? $entry['cover_image']
                            : (is_array($entry['main_image'] ?? null) ? $entry['main_image'] : []));
                    $listing = is_array($entry['listing_content'] ?? null) ? $entry['listing_content'] : [];
                    if ($primary === [] && is_array($listing['image'] ?? null)) {
                        $primary = $listing['image'];
                    }
                    if (($primary['url'] ?? '') === '') {
                        $primary = [];
                    }
                    $hover = is_array($listing['hover_image'] ?? null) ? $listing['hover_image'] : [];
                    $hoverUrl = (string) ($hover['url'] ?? '');
                    if ($hoverUrl === '' || self::isMissingLegacyHover($hoverUrl) || ! preg_match('/\.(?:png|jpe?g|webp|gif)(?:\?.*)?$/i', $hoverUrl)) {
                        $hover = [];
                    }
                    $title = (string) ($entry['title'] ?? $entry['localized']['title'] ?? '');
                    $slug = trim((string) ($entry['slug'] ?? $entry['localized']['slug'] ?? ''), '/');
                    $legacy = self::legacyTeamMember($title);

                    if ($filterNames !== [] && ! in_array(mb_strtolower(trim($title)), $filterNames, true)) {
                        continue;
                    }
                    $normalizedTitle = mb_strtolower(trim($title));
                    if (isset($seenNames[$normalizedTitle])) {
                        continue;
                    }
                    $seenNames[$normalizedTitle] = true;

                    if ($primary === [] && $legacy !== null) {
                        $primary = ['url' => $legacy['primary'], 'alt' => $title];
                    }
                    if ($legacy !== null && $legacy['hover'] !== '') {
                        // The legacy page uses the real hover filename in the
                        // inline mouseover handler. Prefer that source over
                        // stale imported block data.
                        $hover = ['url' => $legacy['hover'], 'alt' => $title];
                    } elseif ($hover === [] && $legacy !== null) {
                        $hover = $primary;
                    }

                    if ($title === '') {
                        continue;
                    }

                    $members[] = [
                        'title' => $title,
                        'position' => $legacy['position'] ?? (string) ($entry['excerpt'] ?? ''),
                        'roles' => $legacy['roles'] ?? [],
                        'email' => $legacy['email'] ?? '',
                        'image' => $primary,
                        'hover_image' => $hover !== [] ? $hover : $primary,
                        'url' => $slug !== ''
                            ? lang_url('/' . trim($collectionKey, '/') . '/' . $slug, $this->lang)
                            : ($legacy !== null ? lang_url('/' . trim($collectionKey, '/') . '/' . self::slug($title), $this->lang) : ''),
                    ];
                }
                $members = array_slice($members, 0, $limit);
            } catch (\Throwable) {
                $members = [];
            }
        }

        // Keep the institutional page complete while the editorial collection
        // is being backfilled from the legacy staff registry. Once an entry is
        // created in `personas`, the collection result above wins and supplies
        // its detail URL automatically.
        if ($filterNames !== []) {
            foreach (explode(',', $this->configString('filter_names')) as $requestedName) {
                $requestedName = trim($requestedName);
                $normalizedName = mb_strtolower($requestedName);
                if ($requestedName === '' || isset($seenNames[$normalizedName])) {
                    continue;
                }
                $legacy = self::legacyTeamMember($requestedName);
                if ($legacy === null) {
                    continue;
                }
                $members[] = [
                    'title' => $requestedName,
                    'position' => $legacy['position'],
                    'roles' => $legacy['roles'],
                    'email' => $legacy['email'],
                    'image' => ['url' => $legacy['primary'], 'alt' => $requestedName],
                    'hover_image' => $legacy['hover'] !== ''
                        ? ['url' => $legacy['hover'], 'alt' => $requestedName]
                        : ['url' => $legacy['primary'], 'alt' => $requestedName],
                    'url' => '',
                ];
            }
        }

        if ($requestedOrder !== []) {
            usort(
                $members,
                static fn (array $left, array $right): int =>
                    ($requestedOrder[mb_strtolower(trim((string) ($left['title'] ?? '')))] ?? 999)
                    <=> ($requestedOrder[mb_strtolower(trim((string) ($right['title'] ?? '')))] ?? 999)
            );
        }

        return [
            'title' => $this->dataString('title'),
            'description' => $this->dataString('description'),
            'members' => $members,
            'columns' => $this->configString('columns', '3'),
            'cssClass' => $this->configString('css_class'),
        ];
    }

    /** @return array{primary: string, hover: string, position: string, roles: list<string>, email: string}|null */
    private static function legacyTeamMember(string $name): ?array
    {
        $base = rtrim((string) env('TEAM_MEDIA_BASE_URL', 'https://teatromuseo.cl/images/team/'), '/') . '/';
        $members = [
            'Víctor Quiroga' => ['victor-quiroga.png', 'victor-quiroga-01.png', ['Payaso', 'Presidente fundación'], 'direccion@teatromuseo.cl'],
            'Paulina Beltrán' => ['paulina-beltran.png', 'paulina-beltran-01.png', ['Titiritera', 'Encargada de proyectos'], 'proyectos@teatromuseo.cl'],
            'Constanza Valenzuela' => ['constanza-valenzuela.png', 'constanza-valenzuela-01.png', ['Diseñadora', 'Encargada de difusión'], 'diseno@teatromuseo.cl'],
            'Diego Zuñiga' => ['6713f9c46f0ef.png', '6713f9c476bd5.png', ['Actor, payaso', 'Encargado de extensión y ventas'], 'extension@teatromuseo.cl'],
            'Diego Zúñiga' => ['6713f9c46f0ef.png', '6713f9c476bd5.png', ['Actor, payaso', 'Encargado de extensión y ventas'], 'extension@teatromuseo.cl'],
            'Claudio Palacios' => ['claudio-palacios.png', 'claudio-palacios-01.png', ['Payaso', 'Secretario Académico'], 'teatroescuela@teatromuseo.cl'],
            'Felipe Lira' => ['felipe-lira.png', 'felipe-lira-01.png', ['Bailarín titiritero', 'Encargado de programación'], 'programacion@teatromuseo.cl'],
            'Tomás Arce' => ['6713faca50701.png', '6713faca5094d.png', ['Gestor cultural', 'Encargado de comunicaciones'], 'difusion@teatromuseo.cl'],
            'Barbara Quiroga' => ['barbara-quiroga.png', 'barbara-quiroga-01.png', ['Secretaria', 'Encargada de sala y museo'], 'sala@teatromuseo.cl'],
            'Kevin Zamora' => ['6713f9d87ce79.png', '6713f9d87d5d7.png', ['Técnico', 'Jefe técnico'], 'tecnico@teatromuseo.cl'],
            'Javiera Silva' => ['67128c3937d9c.png', '67128c39380a9.png', ['Periodista', 'Editora Revista 795'], 'editorial@teatromuseo.cl'],
        ];
        $normalized = mb_strtolower(trim(str_replace('ú', 'u', $name)));

        foreach ($members as $memberName => [$primary, $hover, $roles, $email]) {
            if (mb_strtolower(trim(str_replace('ú', 'u', $memberName))) === $normalized) {
                return [
                    'primary' => $base . $primary,
                    'hover' => $base . $hover,
                    'position' => implode(' · ', $roles),
                    'roles' => $roles,
                    'email' => $email,
                ];
            }
        }

        return null;
    }

    private static function isMissingLegacyHover(string $url): bool
    {
        return in_array($url, [
            'https://teatromuseo.cl/images/team/6713f9c46f0ef-01.png',
            'https://teatromuseo.cl/images/team/6713faca50701-01.png',
            'https://teatromuseo.cl/images/team/6713f9d87ce79-01.png',
            'https://teatromuseo.cl/images/team/67128c3937d9c-01.png',
        ], true);
    }

    private static function slug(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
