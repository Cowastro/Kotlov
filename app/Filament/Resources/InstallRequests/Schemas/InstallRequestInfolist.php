<?php

namespace App\Filament\Resources\InstallRequests\Schemas;

use App\Support\AdminPhoneLinks;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                            ->label('Телефон')
                            ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                            ->html(),
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
                                'heating' => 'Монтаж котла',
                                'heatpump' => 'Монтаж теплового насоса',
                                'fireplace' => 'Монтаж камина',
                                'chimney' => 'Монтаж дымохода',
                                'sauna' => 'Монтаж банной печи',
                                'service' => 'Сервис',
                                'commissioning' => 'Пусконаладка',
                                'engineering' => 'Инженерный подбор',
                                'other' => 'Другое',
                                default => $state,
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
                                'new' => 'info',
                                'accepted' => 'warning',
                                'in_progress' => 'primary',
                                'done' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'new' => 'Новая',
                                'accepted' => 'Принята',
                                'in_progress' => 'В работе',
                                'done' => 'Выполнена',
                                'cancelled' => 'Отменена',
                                default => $state,
                            }),
                        TextEntry::make('price_agreed')
                            ->label('Согласованная цена (BYN)')
                            ->numeric()
                            ->placeholder('-'),
                    ]),

                Section::make('Параметры объекта')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('project_details.property_area')
                            ->label('Площадь дома')
                            ->suffix(' м²')
                            ->placeholder('-'),
                        TextEntry::make('project_details.heating_system')
                            ->label('Система отопления')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'underfloor' => 'Тёплый пол',
                                'radiators' => 'Радиаторы',
                                'mixed' => 'Тёплый пол + радиаторы',
                                'unknown' => 'Пока неизвестно',
                                default => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('project_details.flow_temperature')
                            ->label('Температура подачи')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'up_to_45' => 'До 45 °C',
                                '46_to_60' => '46–60 °C',
                                '61_to_75' => '61–75 °C',
                                'unknown' => 'Пока неизвестно',
                                default => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('project_details.power_supply')
                            ->label('Электропитание')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                '220' => '220 В',
                                '380' => '380 В',
                                'unknown' => 'Пока неизвестно',
                                default => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('project_details.needs_hot_water')
                            ->label('Горячее водоснабжение')
                            ->formatStateUsing(fn ($state) => $state === null ? null : ($state ? 'Нужно' : 'Не нужно'))
                            ->placeholder('-'),
                    ])
                    ->visible(fn ($record) => ! empty($record?->project_details)),

                Section::make('Источник и заметки')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('source')
                            ->label('Источник')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'installers_page' => 'Страница монтажников',
                                'installer_profile' => 'Профиль монтажника',
                                'heat_pump_installation' => 'Страница монтажа тепловых насосов',
                                'heat_pump_catalog' => 'Каталог тепловых насосов',
                                'fireplace_installation' => 'Страница монтажа каминов',
                                'product_engineering_calculation' => 'Инженерный расчёт из карточки товара',
                                'pellet_burner_promo' => 'Акция KOTLOV XO Ceramic PRO',
                                'pellet_burner_evo_promo' => 'Распродажа KOTLOV XO EVO 26 кВт',
                                'pellet_burner_hotta_promo' => 'Распродажа HOTTA Ceramik 20/30 кВт',
                                'product_page' => 'Карточка товара',
                                'cart' => 'Корзина',
                                'admin' => 'Админка',
                                'other' => 'Другое',
                                default => $state,
                            })
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label('Заметки')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Рассылка монтажникам')
                    ->schema([
                        TextEntry::make('leads_summary')
                            ->hiddenLabel()
                            ->state(fn ($record): HtmlString => self::leadsSummary($record))
                            ->html(),
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

    /** Who the request was offered to by e-mail, who opened it and who took it. */
    public static function leadsSummary($record): HtmlString
    {
        $leads = $record->leads()->with('installerProfile:id,company_name,contact_name,phone')->orderByRaw('taken_at is null')->orderBy('taken_at')->orderBy('id')->get();
        if ($leads->isEmpty()) {
            return new HtmlString(e($record->leads_sent_at ? 'Подходящих монтажников не нашлось.' : 'Заявку не рассылали монтажникам.'));
        }

        $time = fn ($at): string => $at ? $at->timezone(config('app.display_timezone', 'Europe/Minsk'))->format('d.m H:i') : '';
        $rows = $leads->map(function ($lead) use ($time): string {
            $profile = $lead->installerProfile;
            $status = match (true) {
                $lead->taken_at !== null => '<strong style="color:#15803d">взял '.$time($lead->taken_at).'</strong>',
                $lead->opened_at !== null => 'открыл '.$time($lead->opened_at),
                $lead->notified_at !== null => 'письмо '.$time($lead->notified_at),
                default => 'не отправлено',
            };

            return '<li>'.e($profile?->display_name ?? '—').($profile?->phone ? ' · '.e($profile->phone) : '').' — '.$status.'</li>';
        })->implode('');

        $taken = $leads->whereNotNull('taken_at')->count();

        return new HtmlString('<p style="margin:0 0 6px">Отправлено: '.$leads->whereNotNull('notified_at')->count().', открыли: '.$leads->whereNotNull('opened_at')->count().', взяли: '.$taken.'</p><ul style="margin:0;padding-left:18px">'.$rows.'</ul>');
    }
}
