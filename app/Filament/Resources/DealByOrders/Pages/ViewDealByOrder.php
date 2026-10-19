<?php

namespace App\Filament\Resources\DealByOrders\Pages;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewDealByOrder extends ViewRecord
{
    protected static string $resource = DealByOrderResource::class;

    public function getTitle(): string
    {
        return 'Заказ Deal.by №'.$this->record->remote_order_id;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
