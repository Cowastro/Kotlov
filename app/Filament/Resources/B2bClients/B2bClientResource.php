<?php

namespace App\Filament\Resources\B2bClients;

use App\Filament\Resources\B2bClients\Pages\CreateB2bClient;
use App\Filament\Resources\B2bClients\Pages\EditB2bClient;
use App\Filament\Resources\B2bClients\Pages\ListB2bClients;
use App\Filament\Resources\B2bClients\Schemas\B2bClientForm;
use App\Models\B2bClient;
use App\Services\B2bClientWorkflow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

class B2bClientResource extends Resource
{
    protected static ?string $model = B2bClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'B2B-клиенты';

    protected static ?string $modelLabel = 'B2B-клиент';

    protected static ?string $pluralModelLabel = 'B2B-клиенты';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?string $slug = 'b2b-clients';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = B2bClient::query()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return B2bClientForm::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'responsibleUser', 'reviewedBy'])
            ->withB2bOrderMetrics();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Компания')->searchable()->sortable()->weight('bold')
                    ->description(fn (B2bClient $record): string => $record->unp ? 'УНП '.$record->unp : 'УНП не указан'),
                TextColumn::make('contact_name')->label('Контакт')->searchable()
                    ->description(fn (B2bClient $record): string => implode(' · ', array_filter([$record->contact_email, $record->contact_phone]))),
                TextColumn::make('partner_type')->label('Тип')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bClient::PARTNER_TYPES[$state] ?? $state),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bClient::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('responsibleUser.name')->label('Менеджер')->placeholder('Не назначен'),
                TextColumn::make('b2b_orders_count')->label('B2B-заказов')->numeric()->sortable(),
                TextColumn::make('b2b_orders_total')->label('Сумма B2B')->money('BYN')->sortable()->placeholder('0 BYN'),
                TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(B2bClient::STATUSES),
                SelectFilter::make('partner_type')->label('Тип клиента')->options(B2bClient::PARTNER_TYPES),
                SelectFilter::make('responsible_user_id')->label('Менеджер')
                    ->relationship('responsibleUser', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('approve')->label('Одобрить')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->visible(fn (B2bClient $record): bool => $record->status !== 'approved')
                    ->action(function (B2bClient $record): void {
                        app(B2bClientWorkflow::class)->approve($record, auth()->user());
                        Notification::make()->success()->title('B2B-доступ одобрен')->send();
                    }),
                Action::make('sendAccess')
                    ->label('Задать пароль')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (B2bClient $record): bool => $record->status === 'approved' && filled($record->user?->email))
                    ->requiresConfirmation()
                    ->modalHeading('Отправить партнёру ссылку для входа?')
                    ->modalDescription(fn (B2bClient $record): string => 'На '.$record->user->email.' придёт безопасная ссылка для задания нового пароля.')
                    ->action(function (B2bClient $record): void {
                        $status = Password::sendResetLink(['email' => $record->user->email]);

                        Notification::make()
                            ->title($status === Password::RESET_LINK_SENT
                                ? 'Ссылка для задания пароля отправлена'
                                : 'Не удалось отправить письмо')
                            ->body($status === Password::RESET_LINK_SENT ? $record->user->email : __($status))
                            ->color($status === Password::RESET_LINK_SENT ? 'success' : 'danger')
                            ->send();
                    }),
                Action::make('pause')->label('Приостановить')->icon('heroicon-o-pause-circle')->color('gray')->requiresConfirmation()
                    ->visible(fn (B2bClient $record): bool => $record->status === 'approved')
                    ->action(function (B2bClient $record): void {
                        app(B2bClientWorkflow::class)->pause($record, auth()->user());
                        Notification::make()->warning()->title('B2B-доступ приостановлен')->send();
                    }),
                Action::make('reject')->label('Отклонить')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (B2bClient $record): bool => $record->status === 'pending')
                    ->action(function (B2bClient $record): void {
                        app(B2bClientWorkflow::class)->reject($record, auth()->user());
                        Notification::make()->danger()->title('Заявка отклонена')->send();
                    }),
                EditAction::make()->label('Карточка'),
            ])
            ->recordUrl(fn (B2bClient $record): string => static::getUrl('edit', ['record' => $record]))
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListB2bClients::route('/'),
            'create' => CreateB2bClient::route('/create'),
            'edit' => EditB2bClient::route('/{record}/edit'),
        ];
    }
}
