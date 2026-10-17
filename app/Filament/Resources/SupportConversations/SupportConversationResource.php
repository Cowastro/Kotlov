<?php

namespace App\Filament\Resources\SupportConversations;

use App\Filament\Resources\SupportConversations\Pages\ListSupportConversations;
use App\Filament\Resources\SupportConversations\Pages\ViewSupportConversation;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportConversationResource extends Resource
{
    protected static ?string $model = SupportConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Онлайн-чат';

    protected static ?string $modelLabel = 'диалог';

    protected static ?string $pluralModelLabel = 'Онлайн-чат';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SupportConversation::query()->where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['latestMessage', 'assignedUser'])
            ->withCount('messages')
            ->withCount([
                'messages as unread_visitor_messages_count' => fn (Builder $query): Builder => $query
                    ->where('sender_type', 'visitor')
                    ->whereNull('read_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('last_message_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->sortable()
                    ->weight(fn (SupportConversation $record): string => $record->status === 'new' ? 'bold' : 'medium'),
                TextColumn::make('name')
                    ->label('Клиент')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (SupportConversation $record): string => $record->phone),
                TextColumn::make('latestMessage.body')
                    ->label('Последнее сообщение')
                    ->limit(90)
                    ->wrap()
                    ->description(fn (SupportConversation $record): string => $record->messages_count
                        ? $record->messages_count.' сообщений'
                        : 'Нет сообщений'),
                TextColumn::make('unread_visitor_messages_count')
                    ->label('Новых')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SupportConversation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'danger',
                        'answered' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('assignedUser.name')
                    ->label('Менеджер')
                    ->placeholder('Не назначен'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(SupportConversation::STATUSES),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('last_message_at', 'desc')
            ->emptyStateHeading('Диалогов пока нет')
            ->emptyStateDescription('Здесь появятся сообщения посетителей из онлайн-чата.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Клиент')->columns(3)->schema([
                TextEntry::make('name')->label('Имя'),
                TextEntry::make('phone')->label('Телефон')->copyable(),
                TextEntry::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => SupportConversation::STATUSES[$state] ?? $state),
                TextEntry::make('page_url')->label('Страница обращения')->url(fn (?string $state) => $state)->openUrlInNewTab()->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Переписка')
                ->description('Ответ менеджера появится у посетителя без перезагрузки страницы.')
                ->extraAttributes(['wire:poll.5s' => 'pollConversation'])
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('messages')->hiddenLabel()->schema([
                        ViewEntry::make('message')
                            ->hiddenLabel()
                            ->view('filament.support-chat.message', fn (SupportMessage $record): array => ['message' => $record])
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportConversations::route('/'),
            'view' => ViewSupportConversation::route('/{record}'),
        ];
    }
}
