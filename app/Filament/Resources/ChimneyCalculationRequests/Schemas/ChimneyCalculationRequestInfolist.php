<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Schemas;

use App\Models\ChimneyCalculationRequest;
use App\Support\AdminPhoneLinks;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ChimneyCalculationRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Запрос')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->label('Номер')->weight('bold'),
                TextEntry::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn ($state) => ChimneyCalculationRequest::STATUSES[$state] ?? $state),
                TextEntry::make('created_at')->label('Получен')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextEntry::make('confirmed_by_name')->label('Подтвердил')->placeholder('—'),
            ]),
            Section::make('Клиент')->columns(2)->schema([
                TextEntry::make('customer_name')->label('Имя')->weight('bold')->columnSpanFull(),
                TextEntry::make('customer_phone')->label('Телефон')->copyable()
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))->html(),
                TextEntry::make('customer_email')->label('Email')->placeholder('—'),
            ]),
            Section::make('Стоимость')->columns(2)->schema([
                TextEntry::make('subtotal')->label('Товары')->money('BYN'),
                TextEntry::make('discount_percent')->label('Скидка')->suffix('%'),
                TextEntry::make('discount')->label('Сумма скидки')->money('BYN'),
                TextEntry::make('total')->label('Итого')->money('BYN')->weight('bold'),
            ]),
            Section::make('Состав комплекта')->columnSpanFull()->schema([
                TextEntry::make('items')->label('Позиции')
                    ->state(fn (ChimneyCalculationRequest $record): array => collect($record->items ?? [])
                        ->map(fn (array $item): string => ($item['name'] ?? 'Товар').' × '.(int) ($item['quantity'] ?? 0).' — '.number_format((float) ($item['total'] ?? 0), 2, '.', ' ').' BYN')
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted(),
                TextEntry::make('configuration')->label('Параметры расчёта')->prose()->placeholder('—'),
            ]),
            Section::make('Связи')->columns(2)->columnSpanFull()->collapsed()->schema([
                TextEntry::make('source_url')->label('Исходная страница')->url(fn ($state) => $state)->openUrlInNewTab()->placeholder('—'),
                TextEntry::make('order.number')->label('Созданный заказ')->url(fn ($record) => $record->order_id ? url('/admin/orders/'.$record->order_id) : null)->placeholder('Заказ ещё не создан'),
            ]),
        ]);
    }
}
