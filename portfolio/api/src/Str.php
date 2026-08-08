<?php

declare(strict_types=1);

namespace App;

final class Str
{
    /** URL-tauglicher Slug inkl. korrekter deutscher Umlaut-Behandlung. */
    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', '&' => ' und ',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    /** Kürzt Text auf ganze Wörter – für Meta-Descriptions und Vorschauen. */
    public static function excerpt(string $value, int $length = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        $cut = mb_substr($value, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace === false ? $cut : mb_substr($cut, 0, $lastSpace), " ,.;:-") . '…';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
