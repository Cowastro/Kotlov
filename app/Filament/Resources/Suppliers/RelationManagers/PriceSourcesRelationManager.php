<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Models\SupplierSource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'sources';

    protected static ?string $title = 'Прайсы и остатки';

    protected static ?string $label = 'источник прайса';

    protected static ?string $pluralLabel = 'Прайсы и остатки';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Название')
                ->placeholder('Основной прайс и остатки')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Формат')
                ->options([
                    'google_sheet' => 'Google Таблица',
                    'google_drive' => 'Папка Google Drive',
                    'excel' => 'Excel-файл',
                    'csv' => 'CSV-файл',
                    'api' => 'API',
                    'email' => 'Файл по email',
                ])
                ->required()
                ->default('excel'),
            TextInput::make('config.url')
                ->label('Ссылка на прайс или папку')
                ->url()
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('schedule_cron')
                ->label('Когда обновляется')
                ->placeholder('Ежедневно в 09:00 или cron-выражение')
                ->helperText('Для менеджеров лучше писать понятное расписание. Технический cron тоже допустим.')
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('Используется')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Прайс / остатки')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label('Формат')
                    ->state(fn (SupplierSource $record): string => $record->typeLabel())
                    ->badge(),
                TextColumn::make('source_url')
                    ->label('Источник')
                    ->state(fn (SupplierSource $record): ?string => $record->sourceUrl())
                    ->placeholder('Ссылка не указана')
                    ->limit(55)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->url(fn (SupplierSource $record): ?string => $record->sourceUrl())
                    ->openUrlInNewTab(),
                TextColumn::make('schedule_cron')
                    ->label('Обновление')
                    ->state(fn (SupplierSource $record): string => $record->scheduleLabel())
                    ->wrap(),
                TextColumn::make('last_run_at')
                    ->label('Последний импорт')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('Ещё не запускался')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Используется')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить прайс или остатки'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Прайсы пока не указаны')
            ->emptyStateDescription('Добавьте ссылку, формат и расписание обновления, чтобы менеджеры видели рабочий источник данных.');
    }
}
