<?php

namespace App\Filament\Resources\B2bClients\Schemas;

use App\Models\B2bClient;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                        ->label('Учетная запись сайта')
                        ->relationship(
                            'user',
                            'email',
                            modifyQueryUsing: fn ($query) => $query->whereIn('role', ['client', 'installer']),
                        )
                        ->searchable(['name', 'email', 'phone'])
                        ->preload()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Select::make('partner_type')
                        ->label('Тип клиента')
                        ->options(B2bClient::PARTNER_TYPES)
                        ->default('wholesale')
                        ->required(),
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
                ->description('Справочные условия для менеджера. Цена на сайте по-прежнему берётся из разрешённого B2B-источника.')
                ->columns(2)
                ->schema([
                    Textarea::make('payment_terms')->label('Условия оплаты')->rows(3),
                    Textarea::make('delivery_terms')->label('Условия доставки')->rows(3),
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
