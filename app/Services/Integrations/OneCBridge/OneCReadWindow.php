<?php

namespace App\Services\Integrations\OneCBridge;

/** Automatic reads run daily during the office's Minsk working hours. */
class OneCReadWindow
{
    public const TIMEZONE = 'Europe/Minsk';

    public static function isOpen(): bool
    {
        $hour = (int) now(self::TIMEZONE)->format('G');

        return $hour >= 9 && $hour < 18;
    }
}
