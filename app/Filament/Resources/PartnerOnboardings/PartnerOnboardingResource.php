<?php

namespace App\Filament\Resources\PartnerOnboardings;

use App\Filament\Resources\B2bClients\B2bClientResource;
use App\Filament\Resources\PartnerOnboardings\Pages\ListPartnerOnboardings;
use App\Models\City;
use App\Models\PartnerOnboarding;
use App\Services\PartnerOnboardingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PartnerOnboardingResource extends Resource
{
    protected static ?string $model = PartnerOnboarding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'Анкеты партнёров';

    protected static ?string $modelLabel = 'анкета партнёра';

    protected static ?string $pluralModelLabel = 'Анкеты партнёров';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageB2bOrders() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = PartnerOnboarding::query()->where('status', 'submitted')->count();

        return $count ? (string) $count : null;
    }

    public static function inviteAction(): Action
    {
        return Action::make('invite')->label('Пригласить партнёра')->icon(Heroicon::OutlinedPaperAirplane)
            ->modalDescription('Партнёру уйдёт письмо с личной ссылкой на анкету.')
            ->schema([
                TextInput::make('email')->label('E-mail партнёра')->email()->required(),
                TextInput::make('company_name')->label('Компания')->maxLength(255),
                TextInput::make('unp')->label('УНП')->maxLength(50),
            ])
            ->action(function (array $data): void {
                app(PartnerOnboardingService::class)->invite($data['email'], $data['company_name'] ?? null, $data['unp'] ?? null, auth()->user());
                Notification::make()->title('Приглашение отправлено')->body($data['email'])->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Компания')->searchable()->weight('bold')->placeholder('—')
                    ->description(fn (PartnerOnboarding $record): string => $record->email),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => PartnerOnboarding::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'opened' => 'info', default => 'gray',
                    }),
                TextColumn::make('type')->label('Тип')
                    ->state(fn (PartnerOnboarding $record): ?string => match ($record->data['partner_type'] ?? null) {
                        'installer' => 'Монтажник', 'wholesale' => 'Магазин / филиал', default => null,
                    })->placeholder('—'),
                TextColumn::make('points')->label('Точки')
                    ->state(fn (PartnerOnboarding $record): string => static::pointCities($record) ?: '—'),
                TextColumn::make('invited_at')->label('Приглашён')->dateTime('d.m.Y', 'Europe/Minsk')->sortable(),
                TextColumn::make('submitted_at')->label('Заполнил')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable()->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Статус')->options(PartnerOnboarding::STATUSES)])
            ->recordActions([
                Action::make('view')->label('Анкета')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->visible(fn (PartnerOnboarding $record): bool => filled($record->data))
                    ->modalHeading(fn (PartnerOnboarding $record): string => 'Анкета: '.$record->company_name)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Закрыть')
                    ->schema(fn (PartnerOnboarding $record): array => static::questionnaire($record)),
                Action::make('approve')->label('Подключить')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (PartnerOnboarding $record): bool => $record->status === 'submitted')
                    ->requiresConfirmation()
                    ->modalDescription('Будет создан одобренный B2B-клиент с точками продаж из анкеты, партнёру уйдёт письмо с доступом в кабинет.')
                    ->action(function (PartnerOnboarding $record): void {
                        try {
                            $client = app(PartnerOnboardingService::class)->approve($record, auth()->user());
                            Notification::make()->title('Партнёр подключён')->body($client->company_name)->success()
                                ->actions([Action::make('client')->label('Карточка B2B-клиента')->url(B2bClientResource::getUrl('edit', ['record' => $client]))])
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                        }
                    }),
                ActionGroup::make([
                    Action::make('copyLink')->label('Ссылка на анкету')->icon(Heroicon::OutlinedLink)
                        ->modalHeading('Личная ссылка на анкету')->modalSubmitAction(false)->modalCancelActionLabel('Закрыть')
                        ->schema(fn (PartnerOnboarding $record): array => [TextInput::make('url')->label('Ссылка')->default($record->url())->readOnly()]),
                    Action::make('resend')->label('Отправить повторно')->icon(Heroicon::OutlinedPaperAirplane)
                        ->visible(fn (PartnerOnboarding $record): bool => $record->isEditableByPartner())
                        ->requiresConfirmation()
                        ->action(function (PartnerOnboarding $record): void {
                            app(PartnerOnboardingService::class)->invite($record->email, $record->company_name, $record->unp, auth()->user());
                            Notification::make()->title('Письмо отправлено повторно')->success()->send();
                        }),
                    Action::make('reject')->label('Отклонить')->icon(Heroicon::OutlinedXCircle)->color('danger')
                        ->visible(fn (PartnerOnboarding $record): bool => $record->isEditableByPartner())
                        ->schema([Textarea::make('reason')->label('Причина')->required()->rows(3)])
                        ->action(function (PartnerOnboarding $record, array $data): void {
                            app(PartnerOnboardingService::class)->reject($record, auth()->user(), $data['reason']);
                            Notification::make()->title('Анкета отклонена')->success()->send();
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /** @return array<int, mixed> */
    public static function questionnaire(PartnerOnboarding $record): array
    {
        $data = $record->data ?? [];
        $cities = City::query()->whereIn('id', collect($data['points'] ?? [])->pluck('city_id'))->pluck('name', 'id');

        return [
            Section::make('Компания')->columns(2)->schema([
                TextEntry::make('company')->label('Название')->state($data['company_name'] ?? '—'),
                TextEntry::make('unp')->label('УНП')->state($data['unp'] ?? '—'),
                TextEntry::make('legal')->label('Юр. адрес')->state($data['legal_address'] ?? '—'),
                TextEntry::make('type')->label('Тип')->state(($data['partner_type'] ?? '') === 'installer' ? 'Монтажная организация' : 'Магазин или филиал'),
                TextEntry::make('contact')->label('Контакт')->state(($data['contact_name'] ?? '—').' · '.($data['phone'] ?? '')),
                TextEntry::make('email')->label('E-mail для входа')->state($data['email'] ?? '—'),
                TextEntry::make('site')->label('Сайт')->state($data['website'] ?? '—'),
                TextEntry::make('about')->label('О компании')->state($data['description'] ?? '—')->columnSpanFull(),
            ]),
            Section::make('Точки продаж')->schema([
                RepeatableEntry::make('points')->hiddenLabel()
                    ->state(collect($data['points'] ?? [])->map(fn (array $point): array => [
                        'city' => $cities[$point['city_id']] ?? '—',
                        'name' => $point['name'] ?? '—',
                        'address' => $point['address'] ?? '—',
                        'phones' => implode(', ', $point['phones'] ?? []),
                        'hours' => $point['working_hours'] ?? '—',
                    ])->all())
                    ->schema([
                        TextEntry::make('city')->label('Город'),
                        TextEntry::make('name')->label('Название'),
                        TextEntry::make('address')->label('Адрес'),
                        TextEntry::make('phones')->label('Телефоны'),
                        TextEntry::make('hours')->label('Режим'),
                    ])->columns(5),
            ]),
        ];
    }

    private static function pointCities(PartnerOnboarding $record): string
    {
        $ids = collect($record->data['points'] ?? [])->pluck('city_id')->filter()->unique();

        return $ids->isEmpty() ? '' : City::query()->whereIn('id', $ids)->pluck('name')->implode(', ');
    }

    public static function getPages(): array
    {
        return ['index' => ListPartnerOnboardings::route('/')];
    }
}
