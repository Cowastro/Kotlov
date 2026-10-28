<?php

namespace App\Filament\Resources\CrmMailboxes;

use App\Filament\Resources\CrmMailboxes\Pages\CreateCrmMailbox;
use App\Filament\Resources\CrmMailboxes\Pages\EditCrmMailbox;
use App\Filament\Resources\CrmMailboxes\Pages\ListCrmMailboxes;
use App\Models\CrmMailbox;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\ImapMailboxSynchronizer;
use App\Services\ServiceMailboxPreviewSender;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CrmMailboxResource extends Resource
{
    protected static ?string $model = CrmMailbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Настройка почты';

    protected static ?string $modelLabel = 'почтовый ящик';

    protected static ?string $pluralModelLabel = 'Настройка почты';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Почта и лиды';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Почтовый ящик')
                ->description('Пароль хранится в базе в зашифрованном виде и не показывается повторно.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Имя отправителя')->required()->default('KOTLOV'),
                    TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                    TextInput::make('username')->label('Логин')->required(),
                    TextInput::make('password')->label('Пароль IMAP/SMTP')->password()->revealable()
                        ->required(fn ($record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('При редактировании оставьте пустым, чтобы не менять пароль.'),
                    Toggle::make('is_enabled')->label('Включить загрузку писем'),
                    Toggle::make('create_leads')->label('Создавать карточку нового лида')->default(true),
                ]),
            Section::make('Доступ')
                ->description('Пусто — общий ящик, его видят все менеджеры. Если выбрать сотрудников, письма ящика видят только они и администраторы.')
                ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                ->schema([
                    Select::make('users')
                        ->label('Сотрудники с доступом')
                        ->relationship('users', 'name', fn (Builder $query): Builder => $query
                            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                            ->where('is_active', true)
                            ->orderBy('name'))
                        ->multiple()
                        ->preload()
                        ->searchable(),
                ]),
            Section::make('IMAP — входящие')->columns(4)->schema([
                TextInput::make('imap_host')->label('Сервер')->required()->default('imap.hoster.by')->columnSpan(2),
                TextInput::make('imap_port')->label('Порт')->numeric()->required()->default(993),
                Select::make('imap_encryption')->label('Шифрование')->options(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Нет'])->default('ssl')->required(),
                TextInput::make('imap_folder')->label('Папка')->default('INBOX')->required()->columnSpan(2),
            ]),
            Section::make('SMTP — ответы')->columns(4)->schema([
                TextInput::make('smtp_host')->label('Сервер')->required()->default('smtp.hoster.by')->columnSpan(2),
                TextInput::make('smtp_port')->label('Порт')->numeric()->required()->default(465),
                Select::make('smtp_encryption')->label('Шифрование')->options(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Нет'])->default('ssl')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Ящик')->weight('bold')->description(fn (CrmMailbox $record): string => $record->email),
            TextColumn::make('purpose')->label('Назначение')->badge()
                ->state(fn (CrmMailbox $record): string => $record->servicePurpose() ?? 'Рабочая переписка')
                ->color(fn (CrmMailbox $record): string => $record->isServiceMailbox() ? 'info' : 'success'),
            TextColumn::make('access')->label('Доступ')->badge()
                ->state(fn (CrmMailbox $record): string => $record->users->isEmpty()
                    ? 'Общий'
                    : $record->users->pluck('name')->implode(', '))
                ->color(fn (CrmMailbox $record): string => $record->users->isEmpty() ? 'gray' : 'warning'),
            IconColumn::make('is_enabled')->label('Синхронизация')->boolean(),
            TextColumn::make('last_success_at')->label('Последняя загрузка')->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('Ещё не запускалась'),
            TextColumn::make('last_error')->label('Состояние')->limit(90)->placeholder('Ошибок нет')->color('danger')->wrap(),
        ])->recordActions([
            Action::make('sync')
                ->label('Проверить почту')
                ->icon('heroicon-o-arrow-path')
                ->action(function (CrmMailbox $record): void {
                    try {
                        $result = app(ImapMailboxSynchronizer::class)->sync($record);
                        Notification::make()->success()->title('Почта проверена')
                            ->body("Загружено: {$result['imported']}; пропущено: {$result['skipped']}.")->send();
                    } catch (Throwable) {
                        Notification::make()->danger()->title('Не удалось проверить почту')
                            ->body('Синхронизация не завершена. Подробности записаны в журнал.')
                            ->send();
                    }
                }),
            Action::make('sendTest')
                ->label('Тест подписи')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->visible(fn (CrmMailbox $record): bool => ! $record->isServiceMailbox())
                ->modalHeading('Отправить тестовое письмо')
                ->modalDescription('Письмо уйдёт от общего ящика, но будет подписано именем, должностью и рабочим телефоном текущего сотрудника.')
                ->modalSubmitActionLabel('Отправить тест')
                ->schema([
                    TextInput::make('recipient')
                        ->label('Кому')
                        ->email()
                        ->required(),
                    Textarea::make('signaturePreview')
                        ->label('Подпись сотрудника')
                        ->default(function (): string {
                            /** @var User $user */
                            $user = auth()->user();

                            return implode("\n", array_filter([
                                $user->name,
                                $user->job_title,
                                $user->phone,
                            ]));
                        })
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->action(function (CrmMailbox $record, array $data): void {
                    try {
                        /** @var User $user */
                        $user = auth()->user();
                        app(CrmMailSender::class)->sendTest($record, $data['recipient'], $user);
                        Notification::make()->success()->title('Тестовое письмо отправлено')->body('Проверьте полученную подпись сотрудника.')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось отправить тест')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('sendServiceTest')
                ->label('Проверить системное письмо')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (CrmMailbox $record): bool => $record->isServiceMailbox())
                ->modalHeading('Отправить системный предпросмотр')
                ->modalDescription('Будет отправлен только фиксированный шаблон этого служебного ящика. Написать произвольное письмо или ответить клиенту отсюда нельзя.')
                ->modalSubmitActionLabel('Отправить предпросмотр')
                ->schema([
                    TextInput::make('recipient')
                        ->label('Кому')
                        ->email()
                        ->required(),
                ])
                ->action(function (CrmMailbox $record, array $data): void {
                    try {
                        app(ServiceMailboxPreviewSender::class)->sendFrom($record, $data['recipient']);
                        Notification::make()->success()->title('Системное письмо отправлено')->body($record->email)->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось отправить тест')->body($exception->getMessage())->send();
                    }
                }),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmMailboxes::route('/'),
            'create' => CreateCrmMailbox::route('/create'),
            'edit' => EditCrmMailbox::route('/{record}/edit'),
        ];
    }
}
