<?php

namespace App\Filament\Resources\B2bClients\Schemas;

use App\Enums\ClientType;
use App\Models\B2bClient;
use App\Models\IntegrationSource;
use App\Models\IntegrationWarehouse;
use App\Models\Supplier;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class B2bClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('B2B-клиент')
                ->description('Отдельная карточка покупателя. Поставщики и CRM-контрагенты сюда не попадают.')
                ->columns(3)
                ->schema([
                    Select::make('user_id')
                        ->label('Аккаунт партнёра на сайте')
                        ->relationship(
                            'user',
                            'email',
                            modifyQueryUsing: fn ($query) => $query->whereIn('role', ['client', 'installer']),
                        )
                        ->searchable(['name', 'email', 'phone'])
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Имя контактного лица')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email для входа')
                                ->email()
                                ->required()
                                ->unique(User::class, 'email')
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Телефон')
                                ->tel()
                                ->maxLength(50),
                        ])
                        ->createOptionUsing(fn (array $data): int => User::query()->create([
                            'name' => trim($data['name']),
                            'email' => mb_strtolower(trim($data['email'])),
                            'phone' => $data['phone'] ?? null,
                            'password' => Str::password(32),
                            'role' => 'client',
                            'client_type' => ClientType::Wholesale,
                            'is_active' => true,
                            'b2b_approved' => false,
                        ])->getKey())
                        ->helperText('Если аккаунта ещё нет, создайте его здесь. После одобрения отправьте партнёру ссылку «Задать пароль».')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Select::make('partner_type')
                        ->label('Тип клиента')
                        ->options(B2bClient::PARTNER_TYPES)
                        ->default('wholesale')
                        ->required(),
                    Select::make('b2b_price_level_id')
                        ->label('Уровень цены')
                        ->relationship(
                            'priceLevel',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                        )
                        ->searchable()
                        ->preload()
                        ->helperText('Уровень применяется только к готовой продажной B2B-цене.'),
                    Select::make('status')
                        ->label('Статус')
                        ->options(B2bClient::STATUSES)
                        ->default('pending')
                        ->required(),
                    TextInput::make('company_name')->label('Компания')->required()->maxLength(255)->columnSpan(2),
                    TextInput::make('unp')->label('УНП / ИНН')->maxLength(50),
                    TextInput::make('contact_name')->label('Контактное лицо')->maxLength(255),
                    TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                    TextInput::make('contact_phone')->label('Телефон')->tel()->maxLength(50),
                    Select::make('responsible_user_id')
                        ->label('Ответственный менеджер')
                        ->relationship(
                            'responsibleUser',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->whereIn('role', ['admin', 'manager', 'sales_manager']),
                        )
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Коммерческие условия')
                ->description('Отдельно настройте формат обслуживания, цену, склады и индивидуальные условия клиента.')
                ->columns(2)
                ->schema([
                    Select::make('service_mode')
                        ->label('Формат обслуживания')
                        ->options(B2bClient::SERVICE_MODES)
                        ->default('online_warehouse')
                        ->required(),
                    DatePicker::make('commercial_terms_valid_until')
                        ->label('Условия действуют до')
                        ->native(false),
                    Textarea::make('payment_terms')->label('Условия оплаты')->rows(3),
                    Textarea::make('delivery_terms')->label('Условия доставки')->rows(3),
                    TextInput::make('minimum_order_amount')
                        ->label('Минимальная сумма заказа')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('BYN'),
                    TextInput::make('payment_deferment_days')
                        ->label('Отсрочка платежа')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('дней'),
                    TextInput::make('credit_limit')
                        ->label('Кредитный лимит')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('BYN'),
                    Toggle::make('specification_service_enabled')
                        ->label('Комплектация по спецификации')
                        ->helperText('Показывать клиенту возможность передать готовую спецификацию.')
                        ->default(true),
                    Select::make('allowed_integration_source_ids')
                        ->label('Доступные прайсы и поставщики')
                        ->options(fn (): array => IntegrationSource::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Выбранные источники появятся в B2B-каталоге этого клиента. Пусто — не показывать ни один прайс.')
                        ->columnSpanFull(),
                    Select::make('allowed_integration_warehouse_ids')
                        ->label('Доступные склады')
                        ->options(fn (): array => IntegrationWarehouse::query()
                            ->with('source:id,name')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (IntegrationWarehouse $warehouse): array => [
                                $warehouse->id => ($warehouse->source?->name ? $warehouse->source->name.' · ' : '')
                                    .($warehouse->name ?: $warehouse->external_id),
                            ])
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Доступ к складам настраивается независимо от уровня цены.')
                        ->columnSpanFull(),
                    Select::make('allowed_supplier_ids')
                        ->label('Внешние партнёрские склады')
                        ->options(fn (): array => Supplier::query()
                            ->where('is_active', true)
                            ->where('b2b_partner_enabled', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Выдаются отдельно от прайсов 1С. Пусто — внешние склады этому клиенту не показывать.')
                        ->columnSpanFull(),
                ]),
            Section::make('Реквизиты из личного кабинета')
                ->description('Клиент заполняет эти данные в B2B-кабинете. Они используются при подготовке счетов и договоров.')
                ->visible(fn (?B2bClient $record): bool => filled($record?->counterparty_id))
                ->columns(2)
                ->schema([
                    Placeholder::make('counterparty_legal_name')
                        ->label('Полное юридическое название')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->legal_name ?: 'Не заполнено')
                        ->columnSpanFull(),
                    Placeholder::make('counterparty_legal_address')
                        ->label('Юридический адрес')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->legal_address ?: 'Не заполнено'),
                    Placeholder::make('counterparty_postal_address')
                        ->label('Почтовый адрес')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->postal_address ?: 'Не заполнено'),
                    Placeholder::make('counterparty_bank_name')
                        ->label('Банк')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->bank_name ?: 'Не заполнено')
                        ->columnSpanFull(),
                    Placeholder::make('counterparty_bank_bic')
                        ->label('BIC')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->bank_bic ?: 'Не заполнено'),
                    Placeholder::make('counterparty_bank_iban')
                        ->label('IBAN')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->bank_iban ?: 'Не заполнено'),
                    Placeholder::make('counterparty_representative')
                        ->label('ФИО подписанта')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->authorized_representative ?: 'Не заполнено'),
                    Placeholder::make('counterparty_position')
                        ->label('Должность')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->representative_position ?: 'Не заполнено'),
                    Placeholder::make('counterparty_authority_basis')
                        ->label('Действует на основании')
                        ->content(fn (?B2bClient $record): string => $record?->counterparty?->authority_basis ?: 'Не заполнено')
                        ->columnSpanFull(),
                ]),
            Section::make('Заявка и внутренние заметки')
                ->columns(2)
                ->schema([
                    Textarea::make('application_comment')->label('Комментарий клиента')->rows(4),
                    Textarea::make('manager_notes')
                        ->label('Заметки менеджера')
                        ->helperText('Не показываются клиенту.')
                        ->rows(4),
                ]),
        ]);
    }
}
