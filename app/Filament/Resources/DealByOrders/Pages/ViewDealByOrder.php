<?php

namespace App\Filament\Resources\DealByOrders\Pages;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;

class ViewDealByOrder extends ViewOrder
{
    protected static string $resource = DealByOrderResource::class;

    public function getTitle(): string
    {
        return 'Заказ Deal.by №'.$this->record->dealByInbox->remote_order_id;
    }
}
