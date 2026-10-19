<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class AdminPhoneLinks
{
    public static function render(?string $phone): ?HtmlString
    {
        $phone = trim((string) $phone);
        $digits = self::digits($phone);

        if ($phone === '' || strlen($digits) < 7) {
            return null;
        }

        $international = '+'.$digits;
        $telegram = 'https://t.me/+'.$digits.'?profile';
        $viber = 'viber://chat?number='.rawurlencode($international);

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:5px">'
            .'<a href="tel:'.e($international).'" style="font-weight:600;text-decoration:none">'.e($phone).'</a>'
            .'<span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;line-height:1">'
            .self::action('WA', 'WhatsApp', 'https://wa.me/'.$digits, '#16a34a')
            .self::action('V', 'Viber', $viber, '#7c3aed')
            .self::action('TG', 'Telegram', $telegram, '#0284c7')
            .'</span></span>'
        );
    }

    public static function renderList(?string $phones): ?HtmlString
    {
        $phones = trim((string) $phones);
        if ($phones === '') {
            return null;
        }

        preg_match_all('/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u', $phones, $matches);
        $items = collect($matches[0] ?? [])
            ->map(fn (string $phone): string => trim($phone))
            ->unique(fn (string $phone): string => self::digits($phone))
            ->map(fn (string $phone): ?string => self::render($phone)?->toHtml())
            ->filter()
            ->values();

        return $items->isEmpty()
            ? null
            : new HtmlString($items->implode('<br>'));
    }

    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '80')) {
            $digits = '375'.substr($digits, 2);
        }

        return $digits;
    }

    private static function action(string $label, string $title, string $href, string $color): string
    {
        return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer" title="'.e($title).'" aria-label="'.e($title).'"'
            .' style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 4px;border:1px solid '.e($color).';border-radius:6px;color:'.e($color).';font-weight:700;text-decoration:none">'
            .e($label).'</a>';
    }
}
