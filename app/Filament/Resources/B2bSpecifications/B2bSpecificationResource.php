<?php

namespace App\Filament\Resources\B2bSpecifications;

use App\Filament\Resources\B2bSpecifications\Pages\EditB2bSpecification;
use App\Filament\Resources\B2bSpecifications\Pages\ListB2bSpecifications;
use App\Filament\Resources\B2bSpecifications\RelationManagers\ItemsRelationManager;
use App\Models\B2bSpecification;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class B2bSpecificationResource extends Resource
{
    protected static ?string $model = B2bSpecification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Спецификации';

    protected static ?string $modelLabel = 'B2B-спецификация';

    protected static ?string $pluralModelLabel = 'B2B-спецификации';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = B2bSpecification::query()->whereIn('status', ['submitted', 'clarification'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Заявка клиента')
                ->columns(2)
                ->schema([
                    Placeholder::make('reference_view')->label('Номер')->content(fn (?B2bSpecification $record): string => $record?->reference ?: '—'),
                    Placeholder::make('company_view')->label('Компания')->content(fn (?B2bSpecification $record): string => $record?->client?->company_name ?: '—'),
                    TextInput::make('title')->label('Название')->disabled()->dehydrated(false)->columnSpanFull(),
                    Textarea::make('comment')->label('Комментарий клиента')->disabled()->dehydrated(false)->rows(3)->columnSpanFull(),
                    Placeholder::make('attachments_view')
                        ->label('Файлы')
                        ->content(function (?B2bSpecification $record): HtmlString|string {
                            if (! $record || $record->attachments->isEmpty()) {
                                return 'Нет файлов';
                            }

                            $links = $record->attachments->map(function ($attachment): string {
                                $url = route('b2b.cabinet.specifications.attachment', $attachment);
                                $name = e($attachment->original_name);
                                $size = number_format($attachment->size / 1024, 0, ',', ' ');

                                return "<a href=\"{$url}\" target=\"_blank\" class=\"text-primary-600 underline\">{$name} ({$size} КБ)</a>";
                            })->join('<br>');

                            return new HtmlString($links);
                        })
                        ->columnSpanFull(),
                    Placeholder::make('parsing_view')
                        ->label('Автоматический разбор')
                        ->content(fn (?B2bSpecification $record): string => $record
                            ? $record->parsingStatusLabel().($record->parse_message ? ': '.$record->parse_message : '')
                            : 'Ожидает разбора')
                        ->columnSpanFull(),
                ]),
            Section::make('Обработка')
                ->columns(2)
                ->schema([
                    Select::make('status')->label('Статус')->options(B2bSpecification::STATUSES)->required(),
                    Select::make('manager_id')
                        ->label('Ответственный менеджер')
                        ->relationship('manager', 'name', modifyQueryUsing: fn (Builder $query) => $query
                            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                            ->where('is_active', true))
                        ->searchable()
                        ->preload(),
                    Select::make('commercial_document_id')
                        ->label('Связанное КП или счёт')
                        ->relationship('commercialDocument', 'number')
                        ->searchable()
                        ->preload(),
                    Textarea::make('client_message')
                        ->label('Сообщение клиенту')
                        ->helperText('Показывается клиенту в B2B-кабинете.')
                        ->rows(3)
                        ->columnSpanFull(),
                    Textarea::make('manager_note')
                        ->label('Внутренняя заметка')
                        ->helperText('Клиент эту заметку не видит.')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'user', 'manager', 'attachments', 'commercialDocument']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Номер')->searchable()->sortable()->weight('bold'),
                TextColumn::make('client.company_name')->label('Компания')->searchable()->sortable()
                    ->description(fn (B2bSpecification $record): string => $record->client?->unp ? 'УНП '.$record->client->unp : ''),
                TextColumn::make('title')->label('Объект / закупка')->searchable()->wrap(),
                TextColumn::make('attachments_count')->label('Файлов')->counts('attachments')->numeric(),
                TextColumn::make('items_count')->label('Позиций')->counts('items')->numeric(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bSpecification::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'submitted', 'clarification' => 'warning',
                        'reviewing' => 'info',
                        'quoted', 'closed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('manager.name')->label('Менеджер')->placeholder('Не назначен'),
                TextColumn::make('commercialDocument.number')->label('КП / счёт')->placeholder('Не создано'),
                TextColumn::make('submitted_at')->label('Получена')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(B2bSpecification::STATUSES),
                SelectFilter::make('manager_id')->label('Менеджер')->relationship('manager', 'name')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make()->label('Обработать')])
            ->recordUrl(fn (B2bSpecification $record): string => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListB2bSpecifications::route('/'),
            'edit' => EditB2bSpecification::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }
}
