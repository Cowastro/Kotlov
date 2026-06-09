<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoHtmlOrLinks implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        $patterns = [
            '/<[^>]+>/u',
            '/\b(?:https?:\/\/|www\.)\S+/iu',
            '/\bhref\s*=/iu',
            '/\[\/?url\b[^\]]*\]/iu',
            '/\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:by|ru|com|net|org|info|biz|io|me|site|online|shop|xyz|top|club)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('Поле не должно содержать HTML-теги или ссылки.');
                return;
            }
        }
    }
}
