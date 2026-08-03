<?php

declare(strict_types=1);

namespace App\Support;

class Slug
{
    /**
     * Generate a URL-friendly slug from a string.
     */
    public static function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        $ascii = null;
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $ascii = preg_replace('/\p{Mn}+/u', '', $normalized);
            }
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        }

        if (! is_string($ascii) || $ascii === '') {
            $ascii = $value;
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $ascii);
        if (! is_string($slug)) {
            return '';
        }

        return trim(mb_strtolower($slug), '-');
    }
}
