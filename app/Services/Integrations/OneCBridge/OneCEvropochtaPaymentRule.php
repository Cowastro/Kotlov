<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;

class OneCEvropochtaPaymentRule extends OneCCarrierPaymentRule
{
    public const CUSTOMER = 'Розничный покупатель Европочта';
    public const AGREEMENT = 'СБГ бай (Розничный покупатель)';
    public const CARRIER = 'Европочты';
    public const FIELDS = ['Партнер_Key', 'Контрагент_Key', 'Соглашение_Key'];

    public function applies(Order $order): bool
    {
        return $order->delivery_type === 'evropochta' && $order->isPaymentOnReceipt();
    }
}
