<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\Orders\QuickOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use RuntimeException;

/**
 * «Быстрый заказ» on the orders list: a manager takes an order by phone in
 * one short form — client, items found in the catalogue with their price,
 * delivery, payment — and it lands in the work queue like a website order.
 */
final class QuickOrderAction
{
    public static function make(): Action
    {
        return Action::make('quick_order')
            ->label('Быстрый заказ')
            ->icon('heroicon-o-bolt')
            ->color('primary')
            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isManager())
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading('Быстрый заказ')
            ->modalDescription('Заказ по телефону или в мессенджере: клиент, товары, доставка и оплата. Остальное можно дополнить в карточке заказа.')
            ->modalSubmitActionLabel('Создать заказ')
            ->form([
                Section::make('Клиент')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('customer_phone')->label('Телефон')->tel()->required()->placeholder('+375 (29) 000-00-00')->autofocus(),
                        TextInput::make('customer_name')->label('Имя')->required()->maxLength(255),
                        TextInput::make('customer_email')->label('Email')->email()->maxLength(255),
                    ]),
                ]),
                Section::make('Товары')->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Добавить товар')
                        ->columns(12)
                        ->live()
                        ->schema([
                            Select::make('product_id')
                                ->label('Товар (поиск по названию, артикулу, бренду)')
                                ->searchable()
                                ->required()
                                ->live()
                                ->getSearchResultsUsing(fn (string $search): array => app(QuickCommercialOfferService::class)->catalogSearchProducts($search))
                                ->getOptionLabelUsing(fn ($value): ?string => app(QuickCommercialOfferService::class)->productLabels([(int) $value])[(int) $value] ?? null)
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $product = $state ? Product::query()->find((int) $state) : null;
                                    if ($product) {
                                        $set('price', round((float) $product->price, 2));
                                    }
                                })
                                ->columnSpan(7),
                            TextInput::make('quantity')->label('Кол-во')->numeric()->integer()->minValue(1)->default(1)->required()->live(onBlur: true)->columnSpan(2),
                            TextInput::make('price')->label('Цена, BYN')->numeric()->minValue(0)->required()->live(onBlur: true)->columnSpan(3),
                        ]),
                    Placeholder::make('items_total')
                        ->label('Сумма товаров')
                        ->content(fn (Get $get): string => number_format(self::itemsTotal((array) $get('items')), 2, ',', ' ').' BYN'),
                ]),
                Section::make('Доставка и оплата')->schema([
                    Grid::make(3)->schema([
                        Select::make('delivery_type')->label('Доставка')->options(Order::DELIVERY_TYPES)->default('pickup')->required()->live()->native(false),
                        TextInput::make('delivery_city')->label('Город')->visible(fn (Get $get): bool => $get('delivery_type') !== 'pickup'),
                        TextInput::make('delivery_price')->label('Стоимость доставки, BYN')->numeric()->minValue(0)->default(0)->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('delivery_type') !== 'pickup'),
                    ]),
                    TextInput::make('delivery_address')->label('Адрес доставки')->visible(fn (Get $get): bool => $get('delivery_type') !== 'pickup'),
                    Grid::make(3)->schema([
                        Select::make('payment_type')->label('Оплата')->options(
                            collect(Order::PAYMENT_TYPES)->except('invoice_deferred')->all()
                        )->default('cash')->required()->live()->native(false),
                        TextInput::make('company_name')->label('Организация')->visible(fn (Get $get): bool => in_array($get('payment_type'), ['invoice', 'bank_transfer'], true)),
                        TextInput::make('company_unp')->label('УНП')->visible(fn (Get $get): bool => in_array($get('payment_type'), ['invoice', 'bank_transfer'], true)),
                    ]),
                ]),
                Grid::make(3)->schema([
                    Select::make('status')->label('Статус')->options(array_intersect_key(Order::STATUSES, array_flip(QuickOrderService::START_STATUSES)))
                        ->default('processing')->required()->native(false),
                    Textarea::make('comment')->label('Комментарий к заказу')->rows(2)->columnSpan(2),
                ]),
                Placeholder::make('order_total')
                    ->label('Итого к оплате')
                    ->content(fn (Get $get): string => number_format(
                        self::itemsTotal((array) $get('items')) + ($get('delivery_type') === 'pickup' ? 0 : max(0, (float) $get('delivery_price'))),
                        2, ',', ' ',
                    ).' BYN'),
            ])
            ->action(function (array $data, Action $action): void {
                abort_unless(auth()->user() instanceof User && auth()->user()->isManager(), 403);
                try {
                    $order = app(QuickOrderService::class)->create($data, auth()->user());
                } catch (RuntimeException $e) {
                    Notification::make()->warning()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }
                Notification::make()->success()
                    ->title('Заказ '.$order->number.' создан')
                    ->body('Сумма '.number_format((float) $order->total, 2, ',', ' ').' BYN. Заказ в работе у вас.')
                    ->send();
                $action->redirect(OrderResource::getUrl('view', ['record' => $order]));
            });
    }

    private static function itemsTotal(array $items): float
    {
        return round(collect($items)->sum(fn ($item): float => max(0, (float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1))), 2);
    }
}
