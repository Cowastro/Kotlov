<?php

namespace App\Filament\Resources\SupplierSyncs;

use App\Filament\Resources\SupplierSyncs\Pages\CreateSupplierSync;
use App\Filament\Resources\SupplierSyncs\Pages\EditSupplierSync;
use App\Filament\Resources\SupplierSyncs\Pages\ListSupplierSyncs;
use App\Models\SupplierSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SupplierSyncResource extends Resource
{
    protected static ?string $model = SupplierSync::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;
    protected static ?string $navigationLabel = 'Обновление товаров';
    protected static ?string $modelLabel = 'обновление поставщика';
    protected static ?string $pluralModelLabel = 'Обновление товаров';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('name')
                ->label('Поставщик')
                ->required()
                ->maxLength(255),

            TextInput::make('code')
                ->label('Код поставщика')
                ->required()
                ->maxLength(255),

            TextInput::make('title')
                ->label('Название')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Описание')
                ->rows(3)
                ->columnSpanFull(),

            TextInput::make('command')
                ->label('Команда обновления')
                ->required()
                ->maxLength(255),

            TextInput::make('source_url')
                ->label('Источник')
                ->url()
                ->maxLength(255),

            TextInput::make('image_disk_path')
                ->label('Папка фото')
                ->placeholder('img/products/elicon')
                ->maxLength(255),

            Toggle::make('is_active')
                ->label('Активен')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (SupplierSync $record) => $record->name),

                TextColumn::make('key')
                    ->label('Код / slug')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->description(fn (SupplierSync $record) => $record->code),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(70)
                    ->wrap(),

                TextColumn::make('products_count')
                    ->label('Кол-во товаров')
                    ->alignRight()
                    ->state(fn (SupplierSync $record) => $record->products_count),

                TextColumn::make('mappings_count')
                    ->label('Кол-во связок')
                    ->alignRight()
                    ->state(fn (SupplierSync $record) => $record->mappings_count),

                TextColumn::make('attributes_count')
                    ->label('Кол-во атрибутов')
                    ->alignRight()
                    ->state(fn (SupplierSync $record) => $record->attributes_count),

                TextColumn::make('photos_count')
                    ->label('Кол-во фото')
                    ->alignRight()
                    ->state(fn (SupplierSync $record) => $record->photos_count),

                TextColumn::make('command')
                    ->label('Команда')
                    ->copyable()
                    ->fontFamily('mono')
                    ->limit(32),

                TextColumn::make('last_run_at')
                    ->label('Последний запуск')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('last_status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'updated' => 'обновлено',
                        'dry-run' => 'проверено',
                        'failed' => 'ошибка',
                        default => 'нет запусков',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'updated' => 'success',
                        'dry-run' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            ->recordActions([
                Action::make('dry_run')
                    ->label('Пробный запуск')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (SupplierSync $record) => self::runSync($record, 'dry_run')),

                Action::make('update')
                    ->label('Полное обновление')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (SupplierSync $record) => self::runSync($record, 'update')),

                Action::make('source')
                    ->label('Открыть источник')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SupplierSync $record) => $record->source_url)
                    ->openUrlInNewTab()
                    ->visible(fn (SupplierSync $record) => filled($record->source_url)),

                Action::make('log')
                    ->label('Посмотреть лог')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(fn (SupplierSync $record) => 'Лог: ' . $record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn (SupplierSync $record) => view('filament.resources.supplier-syncs.log', [
                        'log' => $record->logContents(),
                    ])),

                EditAction::make()
                    ->label('Настройки'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierSyncs::route('/'),
            'create' => CreateSupplierSync::route('/create'),
            'edit' => EditSupplierSync::route('/{record}/edit'),
        ];
    }

    private static function runSync(SupplierSync $record, string $mode): void
    {
        $result = $record->runSync($mode);

        $notification = Notification::make()
            ->title($result['exit_code'] === 0 ? 'Команда завершена' : 'Команда завершилась с ошибками')
            ->body(Str::limit($result['output'] ?: 'Вывод команды пуст.', 900))
            ->persistent();

        $result['exit_code'] === 0 ? $notification->success() : $notification->danger();
        $notification->send();
    }
}
