<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Отклоняет явно нецелевые номера телефонов.
 *
 * Сайт работает в BY/RU/UA — американские (+1) и подобные номера не бывают
 * от реальных клиентов, зато встречаются в каждом спам-запросе.
 */
class PhoneNotSpam implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Нормализуем: только цифры и ведущий +
        $normalized = preg_replace('/[\s\-\(\)\.]/', '', (string) $value);

        // +1 XXXXXXXXXX — US/Canada (10 цифр после +1)
        if (preg_match('/^\+?1\d{10}$/', $normalized)) {
            $fail('Укажите контактный телефон в формате +375 XX XXX-XX-XX.');
            return;
        }

        // Другие явно нецелевые коды: +44 (UK), +61 (AU), +81 (JP) — по 10 цифр после кода
        // Добавляйте по мере появления новых волн спама
        // if (preg_match('/^\+44\d{10}$/', $normalized)) { $fail(...); }
    }
}
