<?php

namespace App\Filament\Resources\InstallerProfiles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallerProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── 1. Основное ───────────────────────────────────────────
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('contact_name')
                            ->label('Контактное лицо')
                            ->maxLength(255),
                        TextInput::make('short_description')
                            ->label('Короткое описание')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('bio')
                            ->label('О компании / монтажнике')
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                'pending'   => 'На проверке',
                                'active'    => 'Активен',
                                'suspended' => 'Приостановлен',
                                'blocked'   => 'Заблокирован',
                            ])
                            ->default('pending')
                            ->required(),
                        Toggle::make('is_verified')
                            ->label('Верифицирован'),
                        Toggle::make('is_published')
                            ->label('Опубликован'),
                    ]),

                // ── 2. Контакты ───────────────────────────────────────────
                Section::make('Контакты')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Основной телефон')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('additional_phone')
                            ->label('Дополнительный телефон')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('website')
                            ->label('Сайт')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('telegram')
                            ->label('Telegram')
                            ->maxLength(255)
                            ->placeholder('@username'),
                        TextInput::make('viber')
                            ->label('Viber')
                            ->maxLength(30),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->maxLength(30),
                    ]),

                // ── 3. Компания ───────────────────────────────────────────
                Section::make('Компания')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Торговое название')
                            ->maxLength(255),
                        TextInput::make('legal_name')
                            ->label('Юридическое название')
                            ->maxLength(255),
                        TextInput::make('unp')
                            ->label('УНП')
                            ->maxLength(20),
                        TextInput::make('address')
                            ->label('Адрес')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                // ── 4. География работы по Беларуси ──────────────────────
                Section::make('География работы по Беларуси')
                    ->columns(2)
                    ->schema([
                        Toggle::make('nationwide')
                            ->label('Работает по всей Беларуси')
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('Основной город')
                            ->maxLength(255),
                        Select::make('region')
                            ->label('Область')
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
                        TextInput::make('work_radius_km')
                            ->label('Радиус выезда (км)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->placeholder('Например: 50'),
                        Select::make('work_regions')
                            ->label('Регионы работы')
                            ->options([
                                'Минск'               => 'Минск',
                                'Минская область'     => 'Минская область',
                                'Гомельская область'  => 'Гомельская область',
                                'Гродненская область' => 'Гродненская область',
                                'Брестская область'   => 'Брестская область',
                                'Витебская область'   => 'Витебская область',
                                'Могилёвская область' => 'Могилёвская область',
                            ])
                            ->multiple()
                            ->columnSpanFull(),
                        TagsInput::make('work_cities')
                            ->label('Города выезда')
                            ->placeholder('Введите город и нажмите Enter')
                            ->columnSpanFull(),
                    ]),

                // ── 5. Специализации ──────────────────────────────────────
                Section::make('Специализации')
                    ->columns(1)
                    ->schema([
                        CheckboxList::make('specializations')
                            ->label('Направления работ')
                            ->options([
                                'heating'       => 'Монтаж котлов',
                                'heatpump'      => 'Монтаж тепловых насосов',
                                'fireplace'     => 'Монтаж каминов',
                                'chimney'       => 'Монтаж дымоходов',
                                'sauna'         => 'Монтаж банных печей',
                                'service'       => 'Сервис котлов',
                                'commissioning' => 'Пусконаладка',
                            ])
                            ->columns(2),
                    ]),

                // ── 6. Медиа и документы ─────────────────────────────────
                Section::make('Медиа и документы')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Фото / аватар')
                            ->image()
                            ->directory('installers/photos')
                            ->maxSize(2048),
                        FileUpload::make('logo')
                            ->label('Логотип компании')
                            ->image()
                            ->directory('installers/logos')
                            ->maxSize(2048),
                        FileUpload::make('gallery')
                            ->label('Галерея работ')
                            ->image()
                            ->multiple()
                            ->directory('installers/gallery')
                            ->maxSize(4096)
                            ->columnSpanFull(),
                        TextInput::make('certificate_photo')
                            ->label('Сертификат (старое поле, URL/путь)')
                            ->maxLength(255),
                        FileUpload::make('certificate_files')
                            ->label('Сертификаты и документы')
                            ->multiple()
                            ->directory('installers/certificates')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                // ── 7. Коммерция и статистика ─────────────────────────────
                Section::make('Коммерция и статистика')
                    ->columns(3)
                    ->schema([
                        TextInput::make('experience_years')
                            ->label('Опыт (лет)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60)
                            ->default(0),
                        TextInput::make('price_from')
                            ->label('Цена от (BYN)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('rating')
                            ->label('Рейтинг')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.01)
                            ->readOnly(),
                        TextInput::make('reviews_count')
                            ->label('Отзывов')
                            ->numeric()
                            ->readOnly(),
                        TextInput::make('orders_count')
                            ->label('Заказов')
                            ->numeric()
                            ->readOnly(),
                    ]),

            ]);
    }
}
