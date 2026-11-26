<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Jobs\SendEvropochtaTrackingSms;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaOrderShipment;
use App\Services\Shipping\EvropochtaPayment;
use App\Services\Shipping\EvropochtaRecipient;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

final class EvropochtaOrderActions
{
    public static function prepare(): Action
    {
        return Action::make('prepareEvropochtaShipment')
            ->label('Подготовить отправление Европочтой')
            ->icon('heroicon-o-clipboard-document-list')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => $record->delivery_type === EvropochtaCheckout::METHOD
                && blank($record->evropochta_tracking_number))
            ->disabled(fn (Order $record): bool => app(EvropochtaOrderShipment::class)->preparationUnavailableReason($record) !== null)
            ->tooltip(fn (Order $record): ?string => app(EvropochtaOrderShipment::class)->preparationUnavailableReason($record))
            ->modalHeading('Подготовить отправление Европочтой')
            ->modalDescription('Выберите отделения и укажите вес и наружные размеры коробки. Тариф сохранится в заказе и войдёт в итог к оплате.')
            ->modalSubmitActionLabel('Рассчитать и сохранить')
            ->fillForm(function (Order $record): array {
                $quote = $record->evropochta_quote ?? [];

                return ($quote['parcel'] ?? []) + EvropochtaRecipient::defaults($record) + [
                    'different_receiver' => filled($quote['recipient'] ?? null),
                    'store_id_start' => config('services.evropochta.checkout_origin'),
                    'store_id_finish' => $quote['store_id_finish'] ?? null,
                ];
            })
            ->schema(fn (): array => [
                ...self::parcelFields(),
                Toggle::make('different_receiver')->label('Получатель отличается от покупателя')->live(),
                Section::make('Получатель отправления')->visible(fn (Get $get): bool => (bool) $get('different_receiver'))
                    ->schema(self::recipientFields()),
            ])
            ->action(function (Order $record, array $data, Action $action, EvropochtaOrderShipment $shipments): void {
                try {
                    $quote = $shipments->prepare($record, $data);
                } catch (EvropochtaShipmentException $error) {
                    Notification::make()->danger()->title($error->getMessage())->send();
                    $action->halt();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Тариф Европочты сохранён')
                    ->body('Доставка в заказе · '.number_format($quote['customer_price'], 0, ',', ' ').' BYN')
                    ->send();
            });
    }

    public static function parcelFields(): array
    {
        $loaded = null;
        $branches = static function () use (&$loaded): array {
            if ($loaded !== null) {
                return $loaded;
            }

            try {
                return $loaded = array_map(static fn (array $branch): string => $branch['label'], app(Evropochta::class)->branches());
            } catch (RuntimeException $error) {
                Notification::make()->danger()->title($error->getMessage())->persistent()->send();

                return $loaded = [];
            }
        };

        return [
            Select::make('store_id_start')->label('Отделение отправления')
                ->options($branches)->searchable()->required()->placeholder('Город или адрес'),
            Select::make('store_id_finish')->label('Отделение получения')
                ->helperText('Выберите отделение по городу, улице или номеру. Адрес доставки заполнится из справочника Европочты.')
                ->options($branches)->searchable()->required()->different('store_id_start')->placeholder('Город или адрес'),
            TextInput::make('weight')->label('Вес в упаковке, кг')->required()->numeric()
                ->minValue(0.01)->maxValue(50)->step(0.01)->rule('decimal:0,2'),
            TextInput::make('length')->label('Длина коробки, см')->required()->numeric()
                ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
            TextInput::make('width')->label('Ширина коробки, см')->required()->numeric()
                ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
            TextInput::make('height')->label('Высота коробки, см')->required()->numeric()
                ->minValue(0.01)->maxValue(250)->step(0.01)->rule('decimal:0,2'),
        ];
    }

    public static function create(): Action
    {
        return Action::make('createEvropochtaShipment')
            ->label('Оформить Европочту и открыть ярлык')
            ->icon('heroicon-o-cube')
            ->authorize(fn (Order $record): bool => OrderResource::canEdit($record))
            ->visible(fn (Order $record): bool => $record->delivery_type === EvropochtaCheckout::METHOD
                && blank($record->evropochta_tracking_number))
            ->disabled(fn (Order $record): bool => app(EvropochtaOrderShipment::class)->unavailableReason($record) !== null)
            ->tooltip(fn (Order $record): ?string => app(EvropochtaOrderShipment::class)->unavailableReason($record))
            ->modalHeading('Оформить Европочту и открыть ярлык')
            ->modalDescription('Проверьте итог для клиента и получателя. Доставку и комиссию Европочты оплачивает наш магазин; дополнительных платежей клиенту нет.')
            ->modalSubmitActionLabel('Оформить и открыть ярлык')
            ->fillForm(fn (Order $record): array => EvropochtaRecipient::defaults($record) + [
                'payment_review_signature' => EvropochtaPayment::signature($record->fresh()),
                'label_format' => 'thermal',
                'notify_receiver' => true,
            ])
            ->schema([
                TextEntry::make('ep_destination')
                    ->label('Отделение получения')
                    ->state(fn (Order $record): string => $record->evropochta_quote['address'] ?? $record->delivery_address ?? '—'),
                TextEntry::make('ep_parcel')
                    ->label('Посылка')
                    ->state(function (Order $record): string {
                        $parcel = $record->evropochta_quote['parcel'];
                        $sides = implode(' × ', [$parcel['length'], $parcel['width'], $parcel['height']]);

                        $dimensions = str_replace('.', ',', (string) $parcel['weight']).' кг · '.str_replace('.', ',', $sides).' см';
                        if (isset($parcel['items'])) {
                            $quantity = array_sum(array_column($parcel['items'], 'quantity'));

                            return $quantity.' шт. в одной коробке · '.$dimensions;
                        }

                        return $dimensions;
                    }),
                ...self::paymentFields(),
                TextEntry::make('ep_name_on_order')
                    ->label('Покупатель в заказе')
                    ->state(fn (Order $record): string => $record->customer_name ?: '—'),
                ...self::recipientFields(),
                Select::make('label_format')->label('Формат ярлыка')->options(Evropochta::LABEL_FORMATS)->required(),
                Toggle::make('notify_receiver')
                    ->label('Отправить получателю СМС с трек-номером')
                    ->helperText(fn (): string => SendEvropochtaTrackingSms::configured()
                        ? 'СМС придёт на указанный телефон получателя.'
                        : 'SMS.BY пока не настроен. Отправление можно оформить.'),
            ])
            ->action(function (Order $record, array $data, Action $action, Component $livewire, EvropochtaOrderShipment $shipments): void {
                try {
                    $number = $shipments->create($record, $data);
                } catch (EvropochtaShipmentException $error) {
                    Notification::make()->danger()->title($error->getMessage())->send();
                    $action->halt();

                    return;
                }

                $record->refresh();
                Notification::make()->success()->title('Трек-номер Европочты сохранён')->body($number)->send();
                $livewire->redirectRoute('admin.orders.evropochta-label', [
                    'order' => $record,
                    'format' => $data['label_format'],
                ]);
            });
    }

    public static function paymentFields(): array
    {
        return [
            TextEntry::make('ep_goods')->label('Товары')
                ->state(fn (Order $record): string => number_format((float) $record->subtotal, 2, ',', ' ').' BYN'),
            TextEntry::make('ep_discount')->label('Скидка')
                ->state(fn (Order $record): string => number_format((float) $record->discount, 2, ',', ' ').' BYN')
                ->visible(fn (Order $record): bool => (float) $record->discount > 0),
            TextEntry::make('ep_delivery')->label('Доставка — уже включена в итог')
                ->state(fn (Order $record): string => number_format((float) $record->delivery_price, 2, ',', ' ').' BYN'),
            TextEntry::make('ep_total')->label('Общий итог заказа')
                ->state(fn (Order $record): string => number_format((float) $record->total, 2, ',', ' ').' BYN')->weight('bold'),
            TextEntry::make('ep_cod')->label('Клиент платит при получении — одной суммой')
                ->state(fn (Order $record): string => number_format(EvropochtaPayment::collect($record), 2, ',', ' ').' BYN')
                ->helperText(fn (Order $record): string => $record->payment_status === 'paid'
                    ? 'Заказ уже оплачен. Повторно деньги с клиента не взыскиваются.'
                    : 'Наложенный платёж включает товары и доставку с учётом скидки. Доплат Европочте нет.'),
            TextEntry::make('ep_payers')->label('Услуги Европочты')
                ->state('Доставку и комиссию наложенного платежа оплачивает наш магазин.'),
            Hidden::make('payment_review_signature'),
        ];
    }

    public static function recipientFields(string $prefix = ''): array
    {
        return [
            TextInput::make($prefix.'receiver_surname')->label('Фамилия получателя')->trim()->required()->minLength(2)->maxLength(100),
            TextInput::make($prefix.'receiver_name')->label('Имя получателя')->trim()->required()->minLength(2)->maxLength(100),
            TextInput::make($prefix.'receiver_patronymic_name')->label('Отчество, если есть')->trim()->maxLength(100),
            TextInput::make($prefix.'receiver_phone_number')->label('Телефон для связи с получателем')->inputMode('tel')
                ->helperText('Может совпадать с телефоном покупателя. Покупатель в заказе не меняется.')
                ->trim()->required()->minLength(7)->maxLength(30)->rule('regex:/^\+?[0-9 ()-]+$/D'),
        ];
    }

    public static function printLabel(string $format): Action
    {
        return Action::make('printEvropochtaLabel_'.$format)
            ->label('Ярлык '.Evropochta::LABEL_FORMATS[$format])
            ->icon('heroicon-o-printer')
            ->authorize(fn (Order $record): bool => OrderResource::canView($record))
            ->visible(fn (Order $record): bool => $record->delivery_type === EvropochtaCheckout::METHOD
                && filled($record->evropochta_tracking_number))
            ->url(fn (Order $record): string => route('admin.orders.evropochta-label', [
                'order' => $record,
                'format' => $format,
            ]))
            ->openUrlInNewTab();
    }

    public static function track(): Action
    {
        return Action::make('trackEvropochtaShipment')
            ->label('Отследить отправление')
            ->icon('heroicon-o-map-pin')
            ->authorize(fn (Order $record): bool => OrderResource::canView($record))
            ->visible(fn (Order $record): bool => $record->delivery_type === EvropochtaCheckout::METHOD
                && filled($record->evropochta_tracking_number))
            ->disabled(fn (): bool => ! app(Evropochta::class)->configured())
            ->modalHeading('Отслеживание Европочты')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрыть')
            ->modalContent(function (Order $record): View {
                $history = [];
                $error = null;
                try {
                    $history = app(Evropochta::class)->trackingHistory($record->evropochta_tracking_number);
                } catch (EvropochtaShipmentException $failure) {
                    $error = $failure->getMessage();
                }

                return view('filament.resources.orders.evropochta-tracking', [
                    'number' => $record->evropochta_tracking_number,
                    'history' => $history,
                    'error' => $error,
                ]);
            });
    }
}
