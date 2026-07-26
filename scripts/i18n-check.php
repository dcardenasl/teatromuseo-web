#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * i18n-check — ci4-website-builder-web (nivelación 2026-07-15)
 *
 * Verifies EN/ES translation parity across:
 *   - Global language files in `app/Language/{en,es}/`
 *   - Per-module language files in `app/Modules/{Module}/Language/{en,es}/`
 *
 * Exits 0 on full parity, 1 on missing files / missing keys / extra keys.
 *
 * Adapted from `ci4-website-builder-admin/scripts/i18n-check.php`. The
 * module scan is a no-op here (web has no app/Modules) but is kept so the
 * script stays byte-compatible with the admin one and keeps working if
 * modules ever appear.
 */

$root = dirname(__DIR__);
$locales = ['en', 'es'];
$errors = [];

/**
 * @param array<string, mixed> $data
 * @return array<string, string>
 */
function flattenKeys(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            foreach (flattenKeys($value, $path) as $nestedKey => $nestedValue) {
                $out[$nestedKey] = $nestedValue;
            }
            continue;
        }
        $out[$path] = (string) $value;
    }

    return $out;
}

/**
 * @param string $localeRoot Directory containing en/, es/ subdirs.
 * @return array{
 *     catalog: array<string, array<string, string>>,
 *     files: array<string, list<string>>
 * }
 */
function buildCatalog(string $localeRoot, array $locales, string $context, array &$errors): array
{
    $catalog = [];
    $filesByLocale = [];

    foreach ($locales as $locale) {
        $dir = $localeRoot . '/' . $locale;
        if (! is_dir($dir)) {
            $errors[] = "[{$context}] Missing locale directory: {$dir}";
            $filesByLocale[$locale] = [];
            continue;
        }

        $languageFiles = glob($dir . '/*.php') ?: [];
        sort($languageFiles);

        $filesByLocale[$locale] = array_map(
            static fn (string $file): string => basename($file),
            $languageFiles
        );

        foreach ($languageFiles as $file) {
            $basename = basename($file, '.php');
            try {
                $data = require $file;
            } catch (\Throwable $e) {
                $errors[] = "[{$context}] ParseError in {$locale}/{$basename}.php: {$e->getMessage()}";
                continue;
            }
            if (! is_array($data)) {
                $errors[] = "[{$context}] Language file does not return array: {$file}";
                continue;
            }
            foreach (flattenKeys($data) as $key => $value) {
                $catalog[$locale]["{$basename}.{$key}"] = $value;
            }
        }
    }

    return ['catalog' => $catalog, 'files' => $filesByLocale];
}

/**
 * @param array<string, list<string>> $filesByLocale
 * @param array<string, array<string, string>> $catalog
 */
function compareCatalogs(array $filesByLocale, array $catalog, array $locales, string $context, array &$errors): void
{
    $baseLocale = $locales[0];
    $baseFiles = $filesByLocale[$baseLocale] ?? [];

    // File parity
    foreach ($locales as $locale) {
        if ($locale === $baseLocale) {
            continue;
        }
        $localeFiles = $filesByLocale[$locale] ?? [];
        foreach (array_diff($baseFiles, $localeFiles) as $file) {
            $errors[] = "[{$context}] Missing language file in {$locale}: {$file}";
        }
        foreach (array_diff($localeFiles, $baseFiles) as $file) {
            $errors[] = "[{$context}] Extra language file in {$locale}: {$file}";
        }
    }

    // Per-file key parity
    $allFiles = [];
    foreach ($filesByLocale as $localeFiles) {
        $allFiles = array_merge($allFiles, $localeFiles);
    }
    $allFiles = array_values(array_unique($allFiles));
    sort($allFiles);

    foreach ($allFiles as $file) {
        $prefix = basename($file, '.php') . '.';
        $keysByLocale = [];
        foreach ($locales as $locale) {
            $localeKeys = [];
            foreach (array_keys($catalog[$locale] ?? []) as $key) {
                if (str_starts_with($key, $prefix)) {
                    $localeKeys[] = $key;
                }
            }
            sort($localeKeys);
            $keysByLocale[$locale] = $localeKeys;
        }

        $baseKeys = $keysByLocale[$baseLocale] ?? [];

        foreach ($locales as $locale) {
            if ($locale === $baseLocale) {
                continue;
            }
            foreach (array_diff($baseKeys, $keysByLocale[$locale]) as $key) {
                $errors[] = "[{$context}] Missing key in {$locale}: {$key}";
            }
            foreach (array_diff($keysByLocale[$locale], $baseKeys) as $key) {
                $errors[] = "[{$context}] Extra key in {$locale}: {$key}";
            }
        }
    }
}

// 1. Global language files
$globalLanguageRoot = $root . '/app/Language';
$globalData = buildCatalog($globalLanguageRoot, $locales, 'app/Language', $errors);
compareCatalogs($globalData['files'], $globalData['catalog'], $locales, 'app/Language', $errors);

// 2. Module language files
$modulesRoot = $root . '/app/Modules';
if (is_dir($modulesRoot)) {
    foreach (glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $moduleName = basename($moduleDir);
        $moduleLanguageRoot = $moduleDir . '/Language';
        if (! is_dir($moduleLanguageRoot)) {
            // Modules without translations are fine — skip.
            continue;
        }
        $moduleData = buildCatalog($moduleLanguageRoot, $locales, "Module/{$moduleName}", $errors);
        compareCatalogs($moduleData['files'], $moduleData['catalog'], $locales, "Module/{$moduleName}", $errors);
    }
}

if ($errors !== []) {
    echo "i18n-check failed:" . PHP_EOL;
    foreach ($errors as $error) {
        echo " - {$error}" . PHP_EOL;
    }
    exit(1);
}

echo 'i18n-check passed' . PHP_EOL;
exit(0);
