<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Concerns\HasOrdersNavigation;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use HasOrdersNavigation;

    protected static string $resource = OrderResource::class;
}
