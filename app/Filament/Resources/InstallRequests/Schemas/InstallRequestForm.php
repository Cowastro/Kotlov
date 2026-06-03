<?php

namespace App\Filament\Resources\InstallRequests\Schemas;

use App\Models\InstallerProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── 1. Клиент ────────────────────────────────────────────
                Section::make('Клиент')
                    ->columns(2)
                    ->schema([
                        Select::make('client_id')
                            ->label('Клиент (аккаунт)')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('customer_name')
                            ->label('Имя клиента')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('customer_phone')
                            ->label('Телефон')
                            ->tel()
                            ->required()
                            ->maxLength(255),
                    ]),

                // ── 2. Заявка ─────────────────────────────────────────────
                Section::make('Заявка')
                    ->columns(2)
                    ->schema([
                        Select::make('specialization')
                            ->label('Тип работ')
                            ->options([
                                'heating'       => 'Монтаж котла',
                                'heatpump'      => 'Монтаж теплового насоса',
                                'fireplace'     => 'Монтаж камина',
                                'chimney'       => 'Монтаж дымохода',
                                'sauna'         => 'Монтаж банной печи',
                                'service'       => 'Сервис',
                                'commissioning' => 'Пусконаладка',
                                'other'         => 'Другое',
                            ])
                            ->nullable(),
                        Select::make('product_id')
                            ->label('Товар')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('region')
                            ->label('Регион')
                            ->options([
                                'Минск'               => 'Минск',
                                'Минская область'     => 'Минская область',
                                'Гомельская область'  => 'Гомельская область',
                                'Гродненская область' => 'Гродненская область',
                                'Брестская область'   => 'Брестская область',
                                'Витебская область'   => 'Витебская область',
                                'Могилёвская область' => 'Могилёвская область',
                            ])
                            ->nullable(),
                        TextInput::make('city')
                            ->label('Город')
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Адрес объекта')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DatePicker::make('preferred_date')
                            ->label('Желаемая дата'),
                        TextInput::make('budget')
                            ->label('Бюджет клиента (BYN)')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('description')
                            ->label('Описание работ')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                // ── 3. Монтажник и статус ────────────────────────────────
                Section::make('Монтажник и статус')
                    ->columns(2)
                    ->schema([
                        Select::make('installer_profile_id')
                            ->label('Профиль монтажника')
                            ->options(
                                InstallerProfile::query()
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->company_name
                                            ?: ($p->contact_name ?: $p->user?->name ?: "Монтажник #{$p->id}"),
                                    ])
                            )
                            ->searchable()
                            ->nullable(),
                        Select::make('installer_id')
                            ->label('Монтажник (аккаунт)')
                            ->relationship('installer', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                'new'         => 'Новая',
                                'accepted'    => 'Принята',
                                'in_progress' => 'В работе',
                                'done'        => 'Выполнена',
                                'cancelled'   => 'Отменена',
                            ])
                            ->default('new')
                            ->required(),
                        TextInput::make('price_agreed')
                            ->label('Согласованная цена (BYN)')
                            ->numeric()
                            ->minValue(0),
                    ]),

                // ── 4. Источник и заметки ────────────────────────────────
                Section::make('Источник и заметки')
                    ->columns(2)
                    ->schema([
                        Select::make('source')
                            ->label('Источник заявки')
                            ->options([
                                'installers_page'  => 'Страница монтажников',
                                'installer_profile' => 'Профиль монтажника',
                                'product_page'     => 'Карточка товара',
                                'cart'             => 'Корзина',
                                'admin'            => 'Админка',
                                'other'            => 'Другое',
                            ])
                            ->nullable(),
                        Textarea::make('notes')
                            ->label('Внутренние заметки')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
