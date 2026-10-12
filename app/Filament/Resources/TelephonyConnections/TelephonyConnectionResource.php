<?php

namespace App\Filament\Resources\TelephonyConnections;

use App\Filament\Resources\TelephonyConnections\Pages\CreateTelephonyConnection;
use App\Filament\Resources\TelephonyConnections\Pages\EditTelephonyConnection;
use App\Filament\Resources\TelephonyConnections\Pages\ListTelephonyConnections;
use App\Models\TelephonyConnection;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelephonyConnectionResource extends Resource
{
    protected static ?string $model = TelephonyConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Настройка A1';

    protected static ?string $modelLabel = 'подключение A1';

    protected static ?string $pluralModelLabel = 'Настройка A1';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Телефония';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Open API ВАТС A1')
                ->description('Данные находятся в разделе A1 «Интеграция с другой CRM». Токены сохраняются зашифрованно.')
                ->columns(2)->schema([
                    Hidden::make('provider')->default('a1'),
                    TextInput::make('company_id')->label('ID клиента (company_id)')->required()->maxLength(100),
                    TextInput::make('primary_api_key')->label('Первичный код интеграции')
                        ->password()->revealable()->autocomplete(false)
                        ->required(fn ($record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('При редактировании оставьте пустым, чтобы сохранить текущий код.'),
                    Toggle::make('is_enabled')->label('Включить Open API и автоматическую сверку')
                        ->helperText('Включайте после сохранения корректных реквизитов.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company_id')->label('Компания A1')->weight('bold'),
            TextColumn::make('webhook_url')->label('URL для Webhook A1')
                ->state(fn (TelephonyConnection $record): string => route('telephony.a1.webhook', $record->webhook_secret))
                ->copyable()
                ->copyableState(fn (TelephonyConnection $record): string => route('telephony.a1.webhook', $record->webhook_secret))
                ->copyMessage('Полный Webhook URL скопирован')
                ->limit(55)
                ->tooltip(fn (TelephonyConnection $record): string => route('telephony.a1.webhook', $record->webhook_secret)),
            IconColumn::make('is_enabled')->label('Open API')->boolean(),
            TextColumn::make('webhook_last_received_at')->label('Последний Webhook')->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('Не получали'),
            TextColumn::make('api_last_success_at')->label('Последняя сверка')->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->placeholder('Не запускалась'),
            TextColumn::make('last_error')->label('Ошибка')->limit(80)->placeholder('Нет')->color('danger'),
        ])->recordActions([EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate() && TelephonyConnection::query()->doesntExist();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelephonyConnections::route('/'),
            'create' => CreateTelephonyConnection::route('/create'),
            'edit' => EditTelephonyConnection::route('/{record}/edit'),
        ];
    }
}
