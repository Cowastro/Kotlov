<?php

namespace App\Filament\Resources\InstallRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Клиент')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Клиент (аккаунт)')
                            ->placeholder('-'),
                        TextEntry::make('customer_name')
                            ->label('Имя клиента'),
                        TextEntry::make('customer_phone')
                            ->label('Телефон'),
                        TextEntry::make('customer_email')
                            ->label('Email клиента')
                            ->placeholder('-'),
                    ]),

                Section::make('Заявка')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('specialization')
                            ->label('Тип работ')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'heating'       => 'Монтаж котла',
                                'heatpump'      => 'Монтаж теплового насоса',
                                'fireplace'     => 'Монтаж камина',
                                'chimney'       => 'Монтаж дымохода',
                                'sauna'         => 'Монтаж банной печи',
                                'service'       => 'Сервис',
                                'commissioning' => 'Пусконаладка',
                                'other'         => 'Другое',
                                default         => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('product.name')
                            ->label('Товар')
                            ->placeholder('-'),
                        TextEntry::make('region')
                            ->label('Регион')
                            ->placeholder('-'),
                        TextEntry::make('city')
                            ->label('Город')
                            ->placeholder('-'),
                        TextEntry::make('address')
                            ->label('Адрес объекта')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('preferred_date')
                            ->label('Желаемая дата')
                            ->date('d.m.Y')
                            ->placeholder('-'),
                        TextEntry::make('budget')
                            ->label('Бюджет клиента (BYN)')
                            ->numeric()
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Описание работ')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Монтажник и статус')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('installerProfile.company_name')
                            ->label('Профиль монтажника')
                            ->placeholder('-'),
                        TextEntry::make('installer.name')
                            ->label('Монтажник (аккаунт)')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'new'         => 'info',
                                'accepted'    => 'warning',
                                'in_progress' => 'primary',
                                'done'        => 'success',
                                'cancelled'   => 'danger',
                                default       => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new'         => 'Новая',
                                'accepted'    => 'Принята',
                                'in_progress' => 'В работе',
                                'done'        => 'Выполнена',
                                'cancelled'   => 'Отменена',
                                default       => $state,
                            }),
                        TextEntry::make('price_agreed')
                            ->label('Согласованная цена (BYN)')
                            ->numeric()
                            ->placeholder('-'),
                    ]),

                Section::make('Источник и заметки')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source')
                            ->label('Источник')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'installers_page'   => 'Страница монтажников',
                                'installer_profile' => 'Профиль монтажника',
                                'product_page'      => 'Карточка товара',
                                'cart'              => 'Корзина',
                                'admin'             => 'Админка',
                                'other'             => 'Другое',
                                default             => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label('Заметки')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Служебное')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Создана')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Обновлена')
                            ->dateTime('d.m.Y H:i'),
                    ]),

            ]);
    }
}
