<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\Orders\QuickOrderService;
use App\Services\Shipping\EvropochtaCheckout;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
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
                        ->afterStateUpdated(self::updateEvropochtaQuote(...))
                        ->schema([
                            Select::make('product_id')
                                ->label('Товар (поиск по названию, артикулу, бренду)')
                                ->searchable()
                                ->required()
                                ->live()
                                ->searchDebounce(250)
                                ->getSearchResultsUsing(self::searchProducts(...))
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
                        Select::make('delivery_type')->label('Доставка')->options(Order::DELIVERY_TYPES)->default('pickup')->required()->live()->native(false)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if ($state === EvropochtaCheckout::METHOD) {
                                    $set('payment_type', EvropochtaCheckout::COD_PAYMENT);
                                } elseif ($get('payment_type') === EvropochtaCheckout::COD_PAYMENT) {
                                    $set('payment_type', 'cash');
                                }
                                self::updateEvropochtaQuote($get, $set);
                            }),
                        TextInput::make('delivery_city')->label('Город')->visible(self::usesAddress(...)),
                        TextInput::make('delivery_price')->label('Стоимость доставки, BYN')->numeric()->minValue(0)->default(0)->live(onBlur: true)
                            ->visible(self::usesAddress(...)),
                    ]),
                    TextInput::make('delivery_address')->label('Адрес доставки')->visible(self::usesAddress(...)),
                    Select::make('evropochta_branch')->label('Отделение получения')
                        ->options(self::evropochtaBranches(...))->searchable()->live()
                        ->placeholder('Введите город, улицу или номер отделения')
                        ->visible(fn (Get $get): bool => $get('delivery_type') === EvropochtaCheckout::METHOD)
                        ->afterStateUpdated(self::updateEvropochtaQuote(...)),
                    Hidden::make('evropochta_token'),
                    Hidden::make('evropochta_price')->dehydrated(false),
                    Hidden::make('evropochta_note')->dehydrated(false),
                    Placeholder::make('evropochta_tariff')->label('Доставка Европочтой')
                        ->visible(fn (Get $get): bool => $get('delivery_type') === EvropochtaCheckout::METHOD)
                        ->content(fn (Get $get): string => $get('evropochta_price') !== null
                            ? number_format((float) $get('evropochta_price'), 0, ',', ' ').' BYN'
                            : ($get('evropochta_note') ?: 'Выберите отделение, чтобы рассчитать тариф.')),
                    Grid::make(3)->schema([
                        Select::make('payment_type')->label('Оплата')->options(fn (Get $get): array =>
                            $get('delivery_type') === EvropochtaCheckout::METHOD
                                ? array_intersect_key(Order::PAYMENT_TYPES, array_flip([EvropochtaCheckout::COD_PAYMENT, EvropochtaCheckout::PAYMENT]))
                                : collect(Order::PAYMENT_TYPES)->except(['invoice_deferred', EvropochtaCheckout::COD_PAYMENT])->all()
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
                    ->label(fn (Get $get): string => $get('delivery_type') === EvropochtaCheckout::METHOD && $get('evropochta_price') === null
                        ? 'Итого без доставки' : 'Итого к оплате')
                    ->content(fn (Get $get): string => number_format(
                        self::itemsTotal((array) $get('items')) + match ($get('delivery_type')) {
                            'pickup' => 0,
                            EvropochtaCheckout::METHOD => (float) $get('evropochta_price'),
                            default => max(0, (float) $get('delivery_price')),
                        },
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

    private static function searchProducts(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $catalog = app(QuickCommercialOfferService::class);
        $ids = Product::query()->active()->notArchived()
            ->whereIn('sku', array_unique([$search, mb_strtoupper($search), mb_strtolower($search)]))
            ->orderByDesc('in_stock')->orderBy('id')->limit(30)->pluck('id')->all();

        return $ids === [] ? $catalog->catalogSearchProducts($search) : $catalog->productLabels($ids);
    }

    private static function usesAddress(Get $get): bool
    {
        return ! in_array($get('delivery_type'), ['pickup', EvropochtaCheckout::METHOD], true);
    }

    private static function evropochtaBranches(): array
    {
        try {
            $branches = app(EvropochtaCheckout::class)->destinations(['store_id_start' => config('services.evropochta.checkout_origin')]);

            return array_map(fn (array $branch): string => $branch['label'], $branches);
        } catch (RuntimeException) {
            return [];
        }
    }

    private static function updateEvropochtaQuote(Get $get, Set $set): void
    {
        if ($get('delivery_type') !== EvropochtaCheckout::METHOD) {
            $set('evropochta_token', null);
            $set('evropochta_price', null);
            $set('evropochta_note', null);

            return;
        }

        ['parcel' => $parcel, 'message' => $message] = app(QuickOrderService::class)->assessEvropochta((array) $get('items'));
        $destination = (int) $get('evropochta_branch');
        $checkout = app(EvropochtaCheckout::class);
        $session = session()->getId();
        $token = $get('evropochta_token');
        $cached = $destination && is_string($token) && $token !== ''
            ? $checkout->confirmedQuote($parcel, $destination, $token, $session)
            : null;
        if ($cached !== null) {
            return;
        }

        $set('evropochta_token', null);
        $set('evropochta_price', null);
        $set('evropochta_note', $message);
        if ($parcel === null || ! $destination) {
            return;
        }

        try {
            $quote = $checkout->quote($parcel, $destination, $session);
            $set('evropochta_token', $quote['token']);
            $set('evropochta_price', $quote['price']);
        } catch (RuntimeException $error) {
            $set('evropochta_note', $error->getMessage());
        }
    }

    private static function itemsTotal(array $items): float
    {
        return round(collect($items)->sum(fn ($item): float => max(0, (float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1))), 2);
    }
}
