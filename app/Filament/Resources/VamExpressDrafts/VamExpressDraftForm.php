<?php

namespace App\Filament\Resources\VamExpressDrafts;

use App\Models\VamExpressDraft;
use App\Services\Shipping\VamExpressDrafts;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressReference;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class VamExpressDraftForm
{
    public static function pricing(): array
    {
        return [
            Section::make('Предварительный расчёт')->description('Цена из калькулятора ВАМ Экспресс. Заявка перевозчику не отправляется.')
                ->columns(['default' => 1, 'md' => 3])->schema([
                    Select::make('profile_id')->label('Профиль отправителя')->searchable()->options(fn (): array => self::safe(fn () => app(VamExpressReference::class)->profiles()))->live(),
                    self::city('city_id', 'Куда доставляем', 'city_label'),
                    Hidden::make('city_label'),
                    Toggle::make('express')->label('Экспресс-доставка')->default(false)->live(),
                    Repeater::make('goods')->label('Грузы')->reorderable(false)->defaultItems(1)->minItems(1)->maxItems(20)->addActionLabel('Добавить груз')->columnSpanFull()
                        ->columns(['default' => 1, 'md' => 6])->schema([
                            Select::make('description')->label('Категория по тарифу ВАМ')->searchable()->options(fn (): array => self::safe(fn () => app(VamExpressReference::class)->goods()))->columnSpan(['default' => 1, 'md' => 2])->live(),
                            TextInput::make('model')->label('Марка / модель')->maxLength(50)->columnSpan(['default' => 1, 'md' => 2]),
                            TextInput::make('number')->label('Кол-во по тарифу')->numeric()->integer()->minValue(1)->maxValue(10000)->default(1)->required()->live(onBlur: true),
                            TextInput::make('declared_price')->label('Оценка за единицу, BYN')->numeric()->minValue(0)->maxValue(1000000)->step(0.01)->default('0.00')->required()->live(onBlur: true),
                        ])->helperText('Категория «Дымоход 10–15 шт.» уже включает комплект: количество 1 означает один такой комплект. Оценка — стоимость груза, а не тариф доставки.'),
                ]),
        ];
    }

    public static function details(): array
    {
        return [
            Section::make('Получатель и адрес')->collapsible()->collapsed()->columns(['default' => 1, 'md' => 3])->schema([
                TextInput::make('recipient_source_name')->label('Имя из заказа — проверьте разбиение ниже')->maxLength(500)->columnSpanFull(),
                TextInput::make('recipient_last_name')->label('Фамилия')->maxLength(30),
                TextInput::make('recipient_first_name')->label('Имя')->maxLength(30),
                TextInput::make('recipient_patronymic')->label('Отчество')->maxLength(30),
                TextInput::make('recipient_phone')->label('Основной телефон')->tel()->maxLength(40)->placeholder('+375291234567'),
                TextInput::make('recipient_phone2')->label('Дополнительный телефон')->tel()->maxLength(40),
                TextInput::make('recipient_city_hint')->label('Город из заказа — выберите населённый пункт для расчёта выше')->maxLength(255),
                Textarea::make('recipient_source_address')->label('Адрес из заказа — проверьте улицу и дом ниже')->rows(2)->maxLength(500)->columnSpanFull(),
                self::prefix('recipient_street_prefix'), TextInput::make('recipient_street')->label('Улица')->maxLength(255)->columnSpan(['default' => 1, 'md' => 2]),
                TextInput::make('recipient_house')->label('Дом')->maxLength(30), TextInput::make('recipient_housing')->label('Корпус')->maxLength(30),
                TextInput::make('recipient_apartment')->label('Квартира / помещение')->maxLength(30),
            ]),
            Section::make('Откуда забрать')->collapsible()->collapsed()->columns(['default' => 1, 'md' => 3])->schema([
                self::city('sender_city_id', 'Населённый пункт отправления', 'sender_city_label'), self::prefix('sender_street_prefix'),
                Hidden::make('sender_city_label'),
                TextInput::make('sender_street')->label('Улица отправителя')->maxLength(255),
                TextInput::make('sender_house')->label('Дом')->maxLength(30), TextInput::make('sender_housing')->label('Корпус')->maxLength(30),
                TextInput::make('sender_apartment')->label('Помещение')->maxLength(30),
            ]),
            Section::make('Места, документы и пожелания')->collapsible()->columns(['default' => 1, 'md' => 3])->schema([
                TextInput::make('boxes')->label('Количество коробок / мест')->integer()->minValue(1)->maxValue(1000)->default(1)->required(),
                DatePicker::make('ship_date')->label('Желаемая дата отправки')->native(false)->displayFormat('d.m.Y'),
                Placeholder::make('documentHint')->label('ТТН')->content('Для юридического лица и договора поручения нужны серия из 2 букв и номер из 7 цифр. Старые условные реквизиты не подставляются.'),
                TextInput::make('ttn_series')->label('Серия ТТН')->maxLength(2), TextInput::make('ttn_number')->label('Номер ТТН')->maxLength(7),
                Textarea::make('other_documents')->label('Другие документы')->rows(2)->maxLength(500),
                Textarea::make('note')->label('Примечания: хрупкость, звонок заранее, часы работы')->maxLength(500)->rows(3)->columnSpanFull(),
            ]),
            Section::make('Проверка сохранённого черновика')->schema([
                Placeholder::make('sourceGoods')->label('Товары заказа')->visible(fn (?VamExpressDraft $record): bool => $record?->order !== null)
                    ->content(fn (?VamExpressDraft $record): string => $record?->order?->items->map(fn ($item) => $item->product_name.' × '.$item->quantity)->implode('; ') ?? ''),
                Placeholder::make('readiness')->label('Готовность к отправке')->content(function (?VamExpressDraft $record): string {
                    if (! $record) {
                        return 'Черновик можно сохранить с незаполненными полями. Расчёт требует профиль, город и категорию каждого груза.';
                    }
                    $missing = app(VamExpressDrafts::class)->missing($record->payload);

                    return $missing ? 'Ещё нужно проверить: '.implode('; ', $missing).'.' : 'Основные поля заполнены. Это черновик KOTLOV; отправление у перевозчика ещё не создано.';
                }),
                Placeholder::make('lastQuote')->label('Последний расчёт')->content(fn (?VamExpressDraft $record): string => $record?->quoted_amount !== null
                    ? number_format((float) $record->quoted_amount, 2, ',', ' ').' BYN · предварительно. После изменения параметров пересчитайте доставку.'
                    : 'Расчёт не сохранён. Нажмите «Сохранить и рассчитать».'),
            ]),
        ];
    }

    private static function city(string $key, string $label, string $labelKey): Select
    {
        return Select::make($key)->label($label)->searchable()->searchDebounce(900)->live()
            ->getSearchResultsUsing(fn (string $search): array => self::safe(fn () => app(VamExpressReference::class)->cities($search)))
            ->getOptionLabelUsing(fn ($value, Get $get): ?string => app(VamExpressReference::class)->city((string) $value)['label'] ?? $get($labelKey))
            ->afterStateUpdated(fn ($state, Set $set) => $set($labelKey, app(VamExpressReference::class)->city((string) $state)['label'] ?? null))
            ->helperText('Введите минимум 2 буквы и выберите точный населённый пункт с районом.');
    }

    private static function prefix(string $key): Select
    {
        return Select::make($key)->label('Тип улицы')->options(array_combine($values = ['ул.', 'аллея', 'бул.', 'дор.', 'линия', 'маг.', 'мик-н', 'наб.', 'пер.', 'пл.', 'пр.', 'пр-кт', 'ряд', 'тракт', 'туп.', 'ш.'], $values))->default('ул.');
    }

    private static function safe(\Closure $load): array
    {
        try {
            return $load();
        } catch (VamExpressException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();

            return [];
        }
    }
}
