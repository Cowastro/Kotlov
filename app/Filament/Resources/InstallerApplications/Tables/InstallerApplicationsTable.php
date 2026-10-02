<?php

namespace App\Filament\Resources\InstallerApplications\Tables;

use App\Filament\Resources\InstallerProfiles\InstallerProfileResource;
use App\Models\InstallerApplication;
use App\Services\InstallerApplicationConverter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallerApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact_name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('experience_years')
                    ->label('Опыт, лет')
                    ->placeholder('-'),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state) => InstallerApplication::$sourceLabels[$state] ?? $state)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'approved' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => InstallerApplication::$statuses[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(InstallerApplication::$statuses),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(InstallerApplication::$sourceLabels),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('createInstallerProfile')
                    ->label('Создать профиль')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Создать профиль монтажника?')
                    ->modalDescription('Данные будут перенесены в черновик профиля. Проверьте контакты, добавьте фото и портфолио, затем опубликуйте профиль вручную.')
                    ->visible(fn ($record) => $record->status === 'approved' && ! $record->installer_profile_id)
                    ->action(function ($record) {
                        $profile = app(InstallerApplicationConverter::class)->convert($record, false);

                        Notification::make()
                            ->success()
                            ->title('Черновик профиля создан')
                            ->body('Теперь добавьте фотографию, описание и портфолио.')
                            ->send();

                        return redirect(InstallerProfileResource::getUrl('edit', ['record' => $profile]));
                    }),
                Action::make('openInstallerProfile')
                    ->label('Профиль')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->visible(fn ($record) => (bool) $record->installer_profile_id)
                    ->url(fn ($record) => InstallerProfileResource::getUrl('edit', [
                        'record' => $record->installer_profile_id,
                    ])),
                Action::make('contacted')
                    ->label('Связались')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'new')
                    ->action(fn ($record) => $record->update(['status' => 'contacted'])),
                Action::make('approved')
                    ->label('Принять')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['new', 'contacted']))
                    ->action(fn ($record) => $record->update(['status' => 'approved'])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
