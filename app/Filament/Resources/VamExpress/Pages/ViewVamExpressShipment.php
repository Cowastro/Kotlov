<?php

namespace App\Filament\Resources\VamExpress\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Models\VamExpressShipment;
use App\Support\Shipping\VamExpressStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewVamExpressShipment extends ViewRecord
{
    protected static string $resource = VamExpressShipmentResource::class;

    public function getTitle(): string
    {
        return 'Отправление ВАМ Экспресс '.$this->record->display_id;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Доставка')->columnSpanFull()->columns(2)->schema([
                TextEntry::make('order.number')->label('Заказ KOTLOV')->placeholder('Не привязан')
                    ->url(fn (VamExpressShipment $record): ?string => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null),
                TextEntry::make('carrier_status')->label('Статус перевозчика')->badge()->placeholder('Статус ещё не проверен')
                    ->color(fn (?string $state): array => VamExpressStatus::color($state)),
                TextEntry::make('city')->label('Город доставки')->placeholder('—'),
                TextEntry::make('synced_at')->label('Обновлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
                TextEntry::make('cargo')->label('Груз')->placeholder('—'),
                TextEntry::make('delivery_cost')->label('Тариф ВАМ')->money('BYN')->placeholder('—'),
            ]),
        ]);
    }
}
