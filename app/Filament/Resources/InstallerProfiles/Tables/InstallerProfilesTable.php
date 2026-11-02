<?php

namespace App\Filament\Resources\InstallerProfiles\Tables;

use App\Models\InstallerProfile;
use App\Services\InstallerB2bConversion;
use App\Support\InstallerMedia;
use App\Support\AdminPhoneLinks;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\HtmlString;

class InstallerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user.b2bClient'))
            ->columns([
                ImageColumn::make('photo')
                    ->label('Фото')
                    ->getStateUsing(fn ($record) => InstallerMedia::url($record->photo))
                    ->circular()
                    ->defaultImageUrl(fn () => null)
                    ->toggleable(),
                TextColumn::make('b2b_state')
                    ->label('B2B')
                    ->badge()
                    ->state(fn (InstallerProfile $record): string => app(InstallerB2bConversion::class)->state($record))
                    ->formatStateUsing(fn (string $state, InstallerProfile $record): string => match ($state) {
                        'invited' => 'Приглашён '.$record->b2b_invited_at?->format('d.m'),
                        'active' => 'Есть кабинет',
                        'no_email' => 'Нет e-mail',
                        default => 'Не подключён',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'invited', 'active' => 'success',
                        'no_email' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('contact_name')
                    ->label('Контакт')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Регион')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
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
                    })
                    ->sortable(),
                TextColumn::make('priority_until')
                    ->label('Приоритет до')
                    ->date('d.m.Y')
                    ->badge()
                    ->color(fn ($state) => $state && $state->isFuture() ? 'warning' : 'gray')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Опубл.')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_verified')
                    ->label('Верифицирован')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('reviews_count')
                    ->label('Отзывов')
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unp')
                    ->label('УНП')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Добавлен')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->searchPlaceholder('Компания, контакт, телефон, email, УНП, город...')
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending'   => 'На проверке',
                        'active'    => 'Активен',
                        'suspended' => 'Приостановлен',
                        'blocked'   => 'Заблокирован',
                    ]),
                TernaryFilter::make('is_published')
                    ->label('Публикация')
                    ->trueLabel('Опубликованные')
                    ->falseLabel('Не опубликованные'),
                TernaryFilter::make('is_verified')
                    ->label('Верификация')
                    ->trueLabel('Верифицированные')
                    ->falseLabel('Не верифицированные'),
                SelectFilter::make('region')
                    ->label('Регион')
                    ->options([
                        'Минск'               => 'Минск',
                        'Минская область'     => 'Минская область',
                        'Гомельская область'  => 'Гомельская область',
                        'Гродненская область' => 'Гродненская область',
                        'Брестская область'   => 'Брестская область',
                        'Витебская область'   => 'Витебская область',
                        'Могилёвская область' => 'Могилёвская область',
                    ]),
                TernaryFilter::make('nationwide')
                    ->label('По всей Беларуси')
                    ->trueLabel('Работает по всей Беларуси')
                    ->falseLabel('Только по региону'),
            ])
            ->recordActions([
                Action::make('cabinetPreview')
                    ->label('Кабинет')
                    ->icon('heroicon-o-computer-desktop')
                    ->url(fn ($record) => route('admin.installer-cabinet-preview', $record))
                    ->openUrlInNewTab(),
                Action::make('connectB2b')
                    ->label(fn (InstallerProfile $record): string => app(InstallerB2bConversion::class)->state($record) === 'none'
                        ? 'Подключить к B2B' : 'Отправить приглашение снова')
                    ->icon('heroicon-o-building-office-2')
                    ->color('success')
                    ->visible(fn (InstallerProfile $record): bool => app(InstallerB2bConversion::class)->state($record) !== 'no_email')
                    ->requiresConfirmation()
                    ->modalHeading('Подключить монтажника к B2B-кабинету')
                    ->modalDescription(fn (InstallerProfile $record): string => 'Будет создан активный B2B-клиент «Монтажная организация» на тот же аккаунт, страница монтажника останется. '
                        .'На '.app(InstallerB2bConversion::class)->email($record).' уйдёт письмо со ссылкой «Задать пароль и войти» (14 дней).')
                    ->action(function (InstallerProfile $record): void {
                        try {
                            app(InstallerB2bConversion::class)->convert($record, auth()->user());
                            Notification::make()->success()->title('Подключён к B2B, приглашение отправлено')->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title('Не подключён')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->persistent()->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('connectB2b')
                        ->label('Подключить к B2B и отправить приглашения')
                        ->icon('heroicon-o-building-office-2')
                        ->requiresConfirmation()
                        ->modalDescription('Каждому выбранному монтажнику с e-mail будет открыт B2B-кабинет и отправлено письмо. Уже подключённым письмо уйдёт повторно, монтажники без e-mail будут пропущены.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $done = 0;
                            $problems = [];
                            foreach ($records as $record) {
                                try {
                                    app(InstallerB2bConversion::class)->convert($record, auth()->user());
                                    $done++;
                                } catch (\Throwable $exception) {
                                    $problems[] = $record->display_name.': '.($exception instanceof ValidationException
                                        ? collect($exception->errors())->flatten()->implode(' ')
                                        : $exception->getMessage());
                                }
                            }
                            Notification::make()->{$problems ? 'warning' : 'success'}()
                                ->title('Подключено и приглашено: '.$done)
                                ->body($problems ? implode("
", $problems) : null)
                                ->persistent((bool) $problems)
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
