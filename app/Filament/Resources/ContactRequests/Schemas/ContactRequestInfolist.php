<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use App\Support\AdminPhoneLinks;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ContactRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Управление обращением')
                    ->description('Текущий этап, ответственный и ближайшее действие')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color(fn (?string $state): string => self::statusColor($state))
                            ->icon(fn (?string $state): ?string => self::statusIcon($state))
                            ->formatStateUsing(fn (?string $state): string => ContactRequest::$statuses[$state] ?? ($state ?: '—')),
                        TextEntry::make('responsibleUser.name')
                            ->label('Ответственный')
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('Не назначен')
                            ->color(fn ($state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('next_follow_up_at')
                            ->label('Следующий контакт')
                            ->icon('heroicon-o-clock')
                            ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                            ->placeholder('Не запланирован')
                            ->color(fn (ContactRequest $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray'),
                        TextEntry::make('created_at')
                            ->label('Поступило')
                            ->icon('heroicon-o-calendar-days')
                            ->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                    ]),

                Section::make('Клиент')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Имя')->weight('bold')->columnSpanFull(),
                        TextEntry::make('phone')
                            ->label('Телефон')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                            ->html(),
                        TextEntry::make('email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->placeholder('Не указан')
                            ->url(fn (?string $state): ?string => $state ? 'mailto:'.$state : null),
                        TextEntry::make('city')->label('Город')->icon('heroicon-o-map-pin')->placeholder('Не указан')->columnSpanFull(),
                    ]),

                Section::make('Обращение')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('message')->label('Что нужно клиенту')->prose()->columnSpanFull(),
                        TextEntry::make('product_name')->label('Товар')->placeholder('Не указан'),
                        TextEntry::make('product_url')
                            ->label('Страница на сайте')
                            ->formatStateUsing(fn (?string $state): string => $state ? 'Открыть страницу товара' : 'Не указана')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->icon('heroicon-o-arrow-top-right-on-square'),
                    ]),

                Section::make('Внутренняя работа')
                    ->description('Видно только сотрудникам')
                    ->icon('heroicon-o-pencil-square')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('admin_notes')
                            ->label('Комментарий / следующий шаг')
                            ->placeholder('Заметок пока нет')
                            ->prose(),
                    ]),

                Section::make('Источник')
                    ->icon('heroicon-o-link')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('source')
                            ->label('Канал')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'product_page' => 'Карточка товара',
                                'consultation_form' => 'Форма консультации',
                                'quick_callback' => 'Быстрый звонок',
                                'ai_selection' => 'AI-подбор',
                                default => $state ?: 'Не указан',
                            }),
                        TextEntry::make('product_id')->label('ID товара')->placeholder('—'),
                        TextEntry::make('updated_at')->label('Последнее изменение')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                    ]),
            ]);
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'new' => 'info',
            'read' => 'gray',
            'in_progress' => 'warning',
            'waiting' => 'primary',
            'done' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    private static function statusIcon(?string $status): ?string
    {
        return match ($status) {
            'new' => 'heroicon-o-sparkles',
            'read' => 'heroicon-o-eye',
            'in_progress' => 'heroicon-o-arrow-path',
            'waiting' => 'heroicon-o-clock',
            'done' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            default => null,
        };
    }
}
