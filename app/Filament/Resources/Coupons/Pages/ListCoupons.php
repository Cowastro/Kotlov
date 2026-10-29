<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Resources\Coupons\CouponResource;
use App\Services\CartCouponService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Dumping mode: the −3% online discount for everyone, on or off in one click.
            Action::make('toggleDumping')
                ->label(fn (): string => CartCouponService::onlineDiscountIsPublic() ? 'Демпинг −3% включён' : 'Демпинг выключен')
                ->icon(fn (): string => CartCouponService::onlineDiscountIsPublic() ? 'heroicon-o-bolt' : 'heroicon-o-pause-circle')
                ->color(fn (): string => CartCouponService::onlineDiscountIsPublic() ? 'danger' : 'gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => CartCouponService::onlineDiscountIsPublic() ? 'Выключить демпинг?' : 'Включить демпинг −3%?')
                ->modalDescription(fn (): string => CartCouponService::onlineDiscountIsPublic()
                    ? 'Скидка −3% пропадёт с сайта: баннер, онлайн-цены на карточках и автоматическая скидка в корзине. Код ONLINE3 перестанет приниматься.'
                    : 'На сайте появятся баннер и онлайн-цены −3%, скидка сама применится в корзине. Тепловые насосы, рассрочка и товары по акции не участвуют, как раньше.')
                ->action(function (): void {
                    $enabled = ! CartCouponService::onlineDiscountIsPublic();
                    CartCouponService::setOnlineDiscount($enabled);
                    Notification::make()->success()->title($enabled ? 'Демпинг −3% включён' : 'Демпинг выключен')->send();
                }),
            CreateAction::make(),
        ];
    }
}
