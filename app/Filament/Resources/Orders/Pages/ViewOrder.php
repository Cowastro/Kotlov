<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function resolveRecord(int | string $key): \App\Models\Order
    {
        return \App\Models\Order::with([
            'items.product.category',
            'statusHistory.user',
            'user',
            'manager',
        ])->findOrFail($key);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
