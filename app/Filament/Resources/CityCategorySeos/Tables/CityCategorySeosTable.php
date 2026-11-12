<?php

namespace App\Filament\Resources\CityCategorySeos\Tables;

use App\Models\CityCategorySeo;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CityCategorySeosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('city.name')->label('Город')->sortable(),
                TextColumn::make('category.name')->label('Категория')
                    ->description(fn (CityCategorySeo $record) => '/'.$record->category?->slug),
                TextColumn::make('meta_title')->label('Title')->limit(60)->placeholder('общий шаблон'),
                TextColumn::make('content')->label('Текст')
                    ->formatStateUsing(fn ($state) => mb_strlen(strip_tags((string) $state)).' симв.')->placeholder('нет'),
                IconColumn::make('is_active')->label('На сайте')->boolean(),
                TextColumn::make('updated_at')->label('Изменено')->dateTime('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('city_id')->label('Город')->relationship('city', 'name'),
            ])
            ->recordActions([
                Action::make('open')->label('Открыть')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (CityCategorySeo $record) => rtrim((string) $record->city?->storefrontUrl(), '/').'/'.$record->category?->slug, shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
