<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaOrderShipment;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

                return ($quote['parcel'] ?? []) + [
                    'store_id_start' => config('services.evropochta.checkout_origin'),
                    'store_id_finish' => $quote['store_id_finish'] ?? null,
                ];
            })
            ->schema(fn (): array => self::parcelFields())
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
            ->modalDescription('Проверьте получателя и выберите формат печати. После оформления ярлык может появиться с задержкой.')
            ->modalSubmitActionLabel('Оформить и открыть ярлык')
            ->fillForm(fn (Order $record): array => [
                'receiver_name' => preg_match('/^\S+$/u', trim((string) $record->customer_name)) ? trim($record->customer_name) : null,
                'receiver_phone_number' => $record->customer_phone,
                'label_format' => 'thermal',
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
                TextEntry::make('ep_cod')
                    ->label('К оплате в отделении')
                    ->state(fn (Order $record): string => number_format((float) $record->total, 2, ',', ' ').' BYN')
                    ->visible(fn (Order $record): bool => $record->payment_type === EvropochtaCheckout::COD_PAYMENT),
                TextEntry::make('ep_name_on_order')
                    ->label('Получатель в заказе')
                    ->state(fn (Order $record): string => $record->customer_name ?: '—'),
                TextInput::make('receiver_name')->label('Имя получателя')->trim()->required()->minLength(2)->maxLength(100),
                TextInput::make('receiver_surname')->label('Фамилия получателя')->trim()->required()->minLength(2)->maxLength(100),
                TextInput::make('receiver_patronymic_name')->label('Отчество, если есть')->trim()->maxLength(100),
                TextInput::make('receiver_phone_number')->label('Телефон получателя')->inputMode('tel')->trim()->required()->maxLength(30),
                Select::make('label_format')->label('Формат ярлыка')->options(Evropochta::LABEL_FORMATS)->required(),
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
