<?php

namespace App\Filament\Supplier\Resources\Orders\Pages;

use App\Filament\Supplier\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
