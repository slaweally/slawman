<?php

declare(strict_types=1);

namespace App\Input;

class InputSanitizer
{
    public function text(?string $value, int $max = 255): string
    {
        $value = trim((string) $value);
        $value = strip_tags($value);

        return mb_substr($value, 0, $max);
    }

    public function multiline(?string $value, int $max = 5000): string
    {
        $value = trim((string) $value);
        $value = strip_tags($value);

        return mb_substr($value, 0, $max);
    }

    public function website(?string $value, int $max = 500): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = strip_tags($value);
        $value = mb_substr($value, 0, $max);

        if (!preg_match('/^https?:\/\//i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
}
