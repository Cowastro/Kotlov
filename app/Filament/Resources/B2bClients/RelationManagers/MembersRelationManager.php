<?php

namespace App\Filament\Resources\B2bClients\RelationManagers;

use App\Models\B2bClientMember;
use App\Services\B2bTeamService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Доступы к кабинету';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->members()->where('is_active', true)->count();

        return $count ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'invitedBy']))
            ->description('Все аккаунты, которые привязаны к этому юридическому лицу и могут входить в его B2B-кабинет.')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->weight('bold')
                    ->placeholder('Имя не указано')
                    ->description(fn (B2bClientMember $record): string => (string) $record->user?->email)
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => B2bClientMember::ROLES[$state] ?? $state),
                IconColumn::make('is_active')->label('Доступ')->boolean(),
                TextColumn::make('joined_at')->label('Подключён')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Создан автоматически'),
                TextColumn::make('invitedBy.name')->label('Кто подключил')->placeholder('Система')->toggleable(),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Изменить роль')
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (B2bClientMember $record): bool => $record->role !== 'owner')
                    ->fillForm(fn (B2bClientMember $record): array => ['role' => $record->role])
                    ->schema([
                        Select::make('role')
                            ->label('Роль в кабинете')
                            ->options(collect(B2bClientMember::ROLES)->except('owner')->all())
                            ->required(),
                    ])
                    ->action(function (B2bClientMember $record, array $data): void {
                        app(B2bTeamService::class)->changeRole($record, auth()->user(), $data['role']);
                        Notification::make()->success()->title('Роль пользователя обновлена')->send();
                    }),
                Action::make('revokeAccess')
                    ->label('Отключить доступ')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (B2bClientMember $record): bool => $record->role !== 'owner' && $record->is_active)
                    ->action(function (B2bClientMember $record): void {
                        app(B2bTeamService::class)->revokeMember($record, auth()->user());
                        Notification::make()->warning()->title('Доступ пользователя отключён')->send();
                    }),
                Action::make('restoreAccess')
                    ->label('Восстановить доступ')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (B2bClientMember $record): bool => $record->role !== 'owner' && ! $record->is_active)
                    ->action(function (B2bClientMember $record): void {
                        app(B2bTeamService::class)->restoreMember($record, auth()->user());
                        Notification::make()->success()->title('Доступ пользователя восстановлен')->send();
                    }),
            ])
            ->emptyStateHeading('К кабинету пока никто не подключён')
            ->emptyStateDescription('Владелец появится здесь после создания карточки, остальные сотрудники — после принятия приглашения.');
    }
}
