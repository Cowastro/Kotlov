<?php

namespace App\Support;

/** A product video as the admin pastes it: a link, or the «Встроить» code of YouTube / Rutube / Vimeo. */
class VideoEmbed
{
    /** «<iframe … src="https://www.youtube.com/embed/ID" …>» → «https://www.youtube.com/embed/ID»; a link stays as it is. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('~<iframe[^>]+src=["\']([^"\']+)["\']~i', $value, $match)) {
            $value = html_entity_decode($match[1]);
        }
        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        return preg_match('~^https?://~i', $value) ? $value : null;
    }
}
