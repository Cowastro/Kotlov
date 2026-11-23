<?php

namespace App\Services\Integrations\OneCBridge;

/**
 * Payment of a customer order comes with its 1C state (OneCOrderStates: «Состояния заказов клиентов» has the sum and
 * the share paid). This keeps the money format of the 1C notes.
 */
class OneCOrderPayments
{
    public static function money(float $sum): string
    {
        return number_format($sum, 2, ',', ' ');
    }
}
