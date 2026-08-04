<?php

declare(strict_types=1);

namespace App\ViewModels\Blocks;

final class TeamGridViewModel extends AbstractBlockViewModel
{
    public function vars(): array
    {
        $members = [];
        foreach ($this->children() as $child) {
            if (($child['block_key'] ?? '') !== 'team_member') {
                continue;
            }

            $data = is_array($child['block_data'] ?? null) ? $child['block_data'] : [];
            $config = is_array($child['block_config'] ?? null) ? $child['block_config'] : [];
            $title = trim((string) ($data['name'] ?? ''));
            if ($title === '') {
                continue;
            }
            $position = trim((string) ($data['position'] ?? ''));
            $profession = trim((string) ($data['profession'] ?? ''));
            $roles = [];
            foreach (is_array($data['roles'] ?? null) ? $data['roles'] : [] as $role) {
                $label = is_array($role)
                    ? trim((string) ($role['label'] ?? $role['name'] ?? ''))
                    : (is_scalar($role) ? trim((string) $role) : '');
                if ($label !== '' && $label !== $position && $label !== $profession) {
                    $roles[] = $label;
                }
            }
            $photo = is_array($config['photo'] ?? null) ? $config['photo'] : [];
            $hoverPhoto = is_array($config['hover_photo'] ?? null) ? $config['hover_photo'] : $photo;

            $members[] = [
                'title' => $title,
                'position' => $position,
                'profession' => $profession,
                'roles' => $roles,
                'email' => trim((string) ($data['email'] ?? '')),
                'image' => $photo,
                'hover_image' => $hoverPhoto,
                'url' => '',
            ];
        }

        return [
            'title' => $this->dataString('title'),
            'description' => $this->dataString('description'),
            'members' => array_slice($members, 0, max(1, min(30, $this->configInt('items_limit', 15)))),
            'columns' => $this->configString('columns', '3'),
            'cssClass' => $this->configString('css_class'),
        ];
    }
}
