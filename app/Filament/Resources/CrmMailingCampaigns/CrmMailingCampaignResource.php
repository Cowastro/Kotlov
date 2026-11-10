<?php

namespace App\Filament\Resources\CrmMailingCampaigns;

use App\Filament\Resources\CrmMailingCampaigns\Pages\CreateCrmMailingCampaign;
use App\Filament\Resources\CrmMailingCampaigns\Pages\EditCrmMailingCampaign;
use App\Filament\Resources\CrmMailingCampaigns\Pages\ListCrmMailingCampaigns;
use App\Filament\Resources\CrmMailingCampaigns\RelationManagers\MessagesRelationManager;
use App\Models\CrmMailingCampaign;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrmMailingCampaignResource extends Resource
{
    protected static ?string $model = CrmMailingCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Viber/SMS рассылки';

    protected static ?string $modelLabel = 'рассылка';

    protected static ?string $pluralModelLabel = 'Viber/SMS рассылки';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()
                ->disabled(fn (?CrmMailingCampaign $record): bool => $record !== null && $record->status !== 'draft')
                ->schema([
                    Select::make('mailing_list_id')->label('Кому — список рассылки')->relationship('mailingList', 'name')->required()->preload()
                        ->default(fn (): ?int => request()->integer('list') ?: null),
                    TextInput::make('name')->label('Название рассылки')->required()->maxLength(255)->placeholder('Новинки ноябрь'),
                    Radio::make('channel')->label('Канал')->options(CrmMailingCampaign::CHANNELS)->default('viber_sms')->required()->live()->columnSpanFull()
                        ->helperText('«Viber, если не доставлено — SMS»: SMS.BY сам отправит SMS тем, у кого Viber не получил сообщение за час.'),
                    Textarea::make('text')->label(fn (Get $get): string => $get('channel') === 'sms' ? 'Текст SMS' : 'Текст Viber-сообщения')
                        ->required()->rows(6)->maxLength(1000)->columnSpanFull()
                        ->helperText('Добавьте в конце «Чтобы не получать сообщения — ответьте СТОП». Ответившие «СТОП» больше не получат рассылок.'),
                    TextInput::make('button_caption')->label('Кнопка — надпись')->maxLength(30)->placeholder('Смотреть на сайте')
                        ->requiredWith('button_link')->visible(fn (Get $get): bool => $get('channel') !== 'sms'),
                    TextInput::make('button_link')->label('Кнопка — ссылка')->url()->maxLength(150)->placeholder('https://kotlov.by/…')
                        ->requiredWith('button_caption')->visible(fn (Get $get): bool => $get('channel') !== 'sms'),
                    Textarea::make('sms_text')->label('Короткий текст для SMS')->rows(3)->maxLength(1000)->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('channel') === 'viber_sms')
                        ->helperText('Если пусто — уйдёт текст Viber и ссылка кнопки. Одно SMS — 70 символов кириллицей, длинное делится на части и стоит дороже.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Рассылка')->weight('bold')->searchable()
                ->description(fn (CrmMailingCampaign $record): string => $record->mailingList?->name ?? ''),
            TextColumn::make('channel')->label('Канал')->formatStateUsing(fn (string $state): string => CrmMailingCampaign::CHANNELS[$state] ?? $state),
            TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => CrmMailingCampaign::STATUSES[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'sending' => 'warning', 'done' => 'success', 'cancelled' => 'danger', default => 'gray',
                }),
            TextColumn::make('recipients_count')->label('Номеров'),
            TextColumn::make('delivered_count')->label('Доставлено')->counts(['messages as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read'])]),
            TextColumn::make('started_at')->label('Запущена')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—')->sortable(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make()->label('Открыть')]);
    }

    public static function getRelations(): array
    {
        return [MessagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmMailingCampaigns::route('/'),
            'create' => CreateCrmMailingCampaign::route('/create'),
            'edit' => EditCrmMailingCampaign::route('/{record}/edit'),
        ];
    }
}
