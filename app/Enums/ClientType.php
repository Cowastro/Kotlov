<?php

namespace App\Enums;

enum ClientType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Installer = 'installer';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Розничный клиент',
            self::Wholesale => 'Оптовый покупатель',
            self::Installer => 'Монтажник',
        };
    }
}