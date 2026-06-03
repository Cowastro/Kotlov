<?php

namespace App\Filament\Resources\InstallerProfiles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallerProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Пользователь'),
                        TextEntry::make('contact_name')
                            ->label('Контактное лицо')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'active'    => 'success',
                                'pending'   => 'warning',
                                'suspended' => 'info',
                                'blocked'   => 'danger',
                                default     => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'active'    => 'Активен',
                                'pending'   => 'На проверке',
                                'suspended' => 'Приостановлен',
                                'blocked'   => 'Заблокирован',
                                default     => $state,
                            }),
                        IconEntry::make('is_verified')
                            ->label('Верифицирован')
                            ->boolean(),
                        IconEntry::make('is_published')
                            ->label('Опубликован')
                            ->boolean(),
                        TextEntry::make('short_description')
                            ->label('Короткое описание')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('bio')
                            ->label('О компании / монтажнике')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Контакты')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('phone')
                            ->label('Основной телефон')
                            ->placeholder('—'),
                        TextEntry::make('additional_phone')
                            ->label('Дополнительный телефон')
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('website')
                            ->label('Сайт')
                            ->placeholder('—'),
                        TextEntry::make('telegram')
                            ->label('Telegram')
                            ->placeholder('—'),
                        TextEntry::make('viber')
                            ->label('Viber')
                            ->placeholder('—'),
                        TextEntry::make('whatsapp')
                            ->label('WhatsApp')
                            ->placeholder('—'),
                    ]),

                Section::make('Компания')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('company_name')
                            ->label('Торговое название')
                            ->placeholder('—'),
                        TextEntry::make('legal_name')
                            ->label('Юридическое название')
                            ->placeholder('—'),
                        TextEntry::make('unp')
                            ->label('УНП')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label('Адрес')
                            ->placeholder('—'),
                    ]),

                Section::make('География работы')
                    ->columns(2)
                    ->schema([
                        IconEntry::make('nationwide')
                            ->label('По всей Беларуси')
                            ->boolean(),
                        TextEntry::make('city')
                            ->label('Основной город')
                            ->placeholder('—'),
                        TextEntry::make('region')
                            ->label('Область')
                            ->placeholder('—'),
                        TextEntry::make('work_radius_km')
                            ->label('Радиус выезда (км)')
                            ->placeholder('—'),
                        TextEntry::make('work_regions')
                            ->label('Регионы работы')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('work_cities')
                            ->label('Города выезда')
                            ->badge()
                            ->placeholder('—'),
                    ]),

                Section::make('Специализации')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('specializations')
                            ->label('Направления работ')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'heating'       => 'Монтаж котлов',
                                'heatpump'      => 'Монтаж тепловых насосов',
                                'fireplace'     => 'Монтаж каминов',
                                'chimney'       => 'Монтаж дымоходов',
                                'sauna'         => 'Монтаж банных печей',
                                'service'       => 'Сервис котлов',
                                'commissioning' => 'Пусконаладка',
                                default         => $state,
                            })
                            ->placeholder('—'),
                    ]),

                Section::make('Медиа и документы')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('photo')
                            ->label('Фото / аватар')
                            ->circular()
                            ->placeholder('—'),
                        ImageEntry::make('logo')
                            ->label('Логотип')
                            ->placeholder('—'),
                        TextEntry::make('gallery')
                            ->label('Галерея (файлы)')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('certificate_photo')
                            ->label('Сертификат (старое поле)')
                            ->placeholder('—'),
                        TextEntry::make('certificate_files')
                            ->label('Сертификаты и документы')
                            ->badge()
                            ->placeholder('—'),
                    ]),

                Section::make('Коммерция и статистика')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('experience_years')
                            ->label('Опыт (лет)'),
                        TextEntry::make('price_from')
                            ->label('Цена от (BYN)')
                            ->numeric()
                            ->placeholder('—'),
                        TextEntry::make('rating')
                            ->label('Рейтинг'),
                        TextEntry::make('reviews_count')
                            ->label('Отзывов'),
                        TextEntry::make('orders_count')
                            ->label('Заказов'),
                    ]),

                Section::make('Служебное')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Обновлён')
                            ->dateTime('d.m.Y H:i'),
                    ]),

            ]);
    }
}
