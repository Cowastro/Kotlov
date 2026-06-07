<?php

namespace App\Filament\Resources\EmailSubscribers;

use App\Filament\Resources\EmailSubscribers\Pages\CreateEmailSubscriber;
use App\Filament\Resources\EmailSubscribers\Pages\EditEmailSubscriber;
use App\Filament\Resources\EmailSubscribers\Pages\ListEmailSubscribers;
use App\Filament\Resources\EmailSubscribers\Pages\ViewEmailSubscriber;
use App\Filament\Resources\EmailSubscribers\Schemas\EmailSubscriberForm;
use App\Filament\Resources\EmailSubscribers\Schemas\EmailSubscriberInfolist;
use App\Filament\Resources\EmailSubscribers\Tables\EmailSubscribersTable;
use App\Models\EmailSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailSubscriberResource extends Resource
{
    protected static ?string $model = EmailSubscriber::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    protected static ?string $navigationLabel = 'Подписчики';
    protected static ?string $modelLabel = 'Подписчик';
    protected static ?string $pluralModelLabel = 'Подписчики';
    protected static ?string $recordTitleAttribute = 'email';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string { return 'Управление'; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_new', true)->count();
        return $count > 0 ? (string) $count : null;
    }
    public static function form(Schema $schema): Schema { return EmailSubscriberForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return EmailSubscriberInfolist::configure($schema); }
    public static function table(Table $table): Table { return EmailSubscribersTable::configure($table); }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index'  => ListEmailSubscribers::route('/'),
            'create' => CreateEmailSubscriber::route('/create'),
            'view'   => ViewEmailSubscriber::route('/{record}'),
            'edit'   => EditEmailSubscriber::route('/{record}/edit'),
        ];
    }
}