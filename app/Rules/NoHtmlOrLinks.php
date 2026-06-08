<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoHtmlOrLinks implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/<[^>]+>|https?:\/\//i', (string) $value)) {
            $fail('Поле не должно содержать HTML-теги или ссылки.');
        }
    }
}
