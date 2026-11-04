<?php

namespace App\Filament\Resources\B2bClients\RelationManagers;

use App\Models\B2bClientInvitation;
use App\Models\B2bClientMember;
use App\Models\User;
use App\Notifications\B2bCompanyInvitationNotification;
use App\Services\B2bTeamService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Приглашения';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();

        return $count ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['inviter', 'acceptedBy'])->latest())
            ->description('Здесь видно, кому отправлена ссылка, принял ли сотрудник приглашение и когда ссылка перестанет работать.')
            ->columns([
                TextColumn::make('email')->label('E-mail')->weight('bold')->searchable(),
                TextColumn::make('role')->label('Роль')->badge()
                    ->formatStateUsing(fn (string $state): string => B2bClientMember::ROLES[$state] ?? $state),
                TextColumn::make('access_status')
                    ->label('Статус')
                    ->badge()
                    ->state(fn (B2bClientInvitation $record): string => $this->status($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Принято' => 'success',
                        'Ожидает' => 'warning',
                        'Отозвано' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('inviter.name')->label('Пригласил')->placeholder('Система'),
                TextColumn::make('created_at')->label('Отправлено')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextColumn::make('expires_at')->label('Действует до')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextColumn::make('acceptedBy.name')->label('Подключённый аккаунт')->placeholder('Ещё не подключён')->toggleable(),
            ])
            ->headerActions([
                Action::make('inviteTeamMember')
                    ->label('Пригласить сотрудника')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->modalHeading('Доступ сотрудника к B2B-кабинету')
                    ->modalDescription('На указанный e-mail будет отправлена одноразовая ссылка. Она действует 7 дней.')
                    ->modalSubmitActionLabel('Отправить приглашение')
                    ->schema([
                        TextInput::make('email')->label('E-mail сотрудника')->email()->required(),
                        Select::make('role')->label('Роль')->options(collect(B2bClientMember::ROLES)->except('owner')->all())
                            ->default('branch_operator')->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $teams = app(B2bTeamService::class);
                        [$invitation, $token] = $teams->invite($this->getOwnerRecord(), $actor, $data['email'], $data['role']);

                        try {
                            NotificationFacade::route('mail', $invitation->email)
                                ->notify(new B2bCompanyInvitationNotification($invitation->load(['client', 'inviter']), $token));
                        } catch (\Throwable $exception) {
                            $teams->revokeInvitation($invitation, $actor);
                            Log::error('Admin B2B company invitation email failed.', [
                                'b2b_client_id' => $this->getOwnerRecord()->id,
                                'invitation_id' => $invitation->id,
                                'exception' => $exception->getMessage(),
                            ]);
                            Notification::make()->danger()->title('Не удалось отправить приглашение')->send();

                            return;
                        }

                        Notification::make()->success()->title('Приглашение отправлено')->body($invitation->email)->send();
                    }),
            ])
            ->recordActions([
                Action::make('revokeInvitation')
                    ->label('Отозвать')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (B2bClientInvitation $record): bool => $record->isAvailable())
                    ->action(function (B2bClientInvitation $record): void {
                        app(B2bTeamService::class)->revokeInvitation($record, auth()->user());
                        Notification::make()->warning()->title('Приглашение отозвано')->send();
                    }),
            ])
            ->emptyStateHeading('Приглашений пока нет')
            ->emptyStateDescription('Отправьте сотруднику ссылку и отслеживайте здесь её статус.');
    }

    private function status(B2bClientInvitation $invitation): string
    {
        if ($invitation->accepted_at) {
            return 'Принято';
        }
        if ($invitation->revoked_at) {
            return 'Отозвано';
        }

        return $invitation->expires_at->isFuture() ? 'Ожидает' : 'Просрочено';
    }
}
