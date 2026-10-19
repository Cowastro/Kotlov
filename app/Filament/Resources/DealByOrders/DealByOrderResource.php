<?php

namespace App\Filament\Resources\DealByOrders;

use App\Filament\Resources\DealByOrders\Pages\EditDealByOrder;
use App\Filament\Resources\DealByOrders\Pages\ListDealByOrders;
use App\Filament\Resources\DealByOrders\Pages\ViewDealByOrder;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class DealByOrderResource extends OrderResource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Заказы Deal.by';

    protected static ?string $modelLabel = 'заказ Deal.by';

    protected static ?string $pluralModelLabel = 'Заказы Deal.by';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return Order::query()->whereHas('dealByInbox');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDealByOrders::route('/'),
            'view' => ViewDealByOrder::route('/{record}'),
            'edit' => EditDealByOrder::route('/{record}/edit'),
        ];
    }
}
