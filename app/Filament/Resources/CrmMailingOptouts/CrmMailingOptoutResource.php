<?php

namespace App\Filament\Resources\CrmMailingOptouts;

use App\Filament\Resources\CrmMailingOptouts\Pages\ListCrmMailingOptouts;
use App\Models\CrmMailingOptout;
use App\Services\Telephony\PhoneNumber;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Phones that must not get Viber/SMS mailings (replied «СТОП» or removed by a manager). */
class CrmMailingOptoutResource extends Resource
{
    protected static ?string $model = CrmMailingOptout::class;

    protected static ?string $modelLabel = 'отписавшийся номер';

    protected static ?string $pluralModelLabel = 'Отписавшиеся от рассылок';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('phone')->label('Телефон')->formatStateUsing(fn (string $state): string => PhoneNumber::format($state) ?? $state)->searchable(),
            TextColumn::make('source')->label('Как')->formatStateUsing(fn (string $state): string => $state === 'viber' ? 'Ответил «СТОП»' : 'Вручную'),
            TextColumn::make('note')->label('Заметка')->placeholder('—')->wrap(),
            TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->defaultSort('created_at', 'desc')
            ->recordActions([DeleteAction::make()->label('Вернуть в рассылки')->modalHeading('Снова присылать рассылки на этот номер?')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCrmMailingOptouts::route('/')];
    }
}
