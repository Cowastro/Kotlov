<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Schemas;

use App\Models\ChimneyCalculationRequest;
use App\Models\Product;
use App\Support\AdminPhoneLinks;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
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
                ViewEntry::make('assembly_schematic')
                    ->hiddenLabel()
                    ->view('filament.chimney-calculation-requests.assembly-schematic', fn (ChimneyCalculationRequest $record): array => [
                        'items' => self::assemblyItems($record),
                        'editable' => $record->status === 'pending',
                    ])
                    ->columnSpanFull(),
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

    private static function assemblyItems(ChimneyCalculationRequest $record): array
    {
        $items = collect($record->items ?? [])->filter(fn ($item): bool => is_array($item));
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->filter()->map(fn ($id): int => (int) $id))
            ->get()
            ->keyBy('id');

        return $items
            ->map(function (array $item, int $index) use ($products): array {
                $product = $products->get((int) ($item['product_id'] ?? 0));

                return [
                    'name' => (string) ($item['name'] ?? 'Товар'),
                    'sku' => (string) ($item['sku'] ?? ''),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'image' => $product?->image_url ?? asset('img/products/product-placeholder.jpg'),
                    'rank' => self::assemblyRank($item),
                    'index' => $index,
                ];
            })
            ->sortBy([['rank', 'asc'], ['index', 'asc']])
            ->values()
            ->all();
    }

    private static function assemblyRank(array $item): int
    {
        if (($item['chimney_selector_selected'] ?? true) === false) {
            return 110;
        }

        $name = mb_strtolower((string) (($item['name'] ?? '').' '.($item['sku'] ?? '')));

        return match (true) {
            str_contains($name, 'зонт'), str_contains($name, 'дефлектор') => 10,
            str_contains($name, 'фартук'), str_contains($name, 'проход кров') => 20,
            str_contains($name, 'растяж'), str_contains($name, 'креплен') => 30,
            str_contains($name, 'хомут') => 40,
            str_contains($name, 'ппу'), str_contains($name, 'проход перекры') => 50,
            str_contains($name, 'термо'), str_contains($name, 'сэндвич'), str_contains($name, 'утепл') => 60,
            str_contains($name, 'переход') => 70,
            str_contains($name, 'бак'), str_contains($name, 'теплообмен') => 80,
            str_contains($name, 'труба'), str_contains($name, 'моно') => 85,
            str_contains($name, 'шибер') => 90,
            str_contains($name, 'адаптер') => 100,
            default => 75,
        };
    }
}
