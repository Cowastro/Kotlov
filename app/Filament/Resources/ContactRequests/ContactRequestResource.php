<?php

namespace App\Filament\Resources\ContactRequests;

use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Pages\ViewContactRequest;
use App\Filament\Resources\ContactRequests\Pages\EditContactRequest;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestInfolist;
use App\Filament\Resources\ContactRequests\Schemas\ContactRequestForm;
use App\Filament\Resources\ContactRequests\Tables\ContactRequestsTable;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\ContactRequest;
use App\Models\SupportConversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactRequestResource extends Resource
{
    protected static ?string $model = ContactRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    protected static ?string $navigationLabel = 'Обращения';
    protected static ?string $modelLabel = 'Обращение';
    protected static ?string $pluralModelLabel = 'Обращения';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string { return 'Продажи'; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema { return ContactRequestForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return ContactRequestInfolist::configure($schema); }
    public static function table(Table $table): Table { return ContactRequestsTable::configure($table); }
    public static function getRelations(): array { return []; }

    public static function siteChatUrl(ContactRequest $record): ?string
    {
        if (! $record->isSiteChat()) {
            return null;
        }

        $chat = SupportConversation::query()->where('public_id', $record->external_id)->firstOrFail();

        return SupportConversationResource::getUrl('view', ['record' => $chat]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactRequests::route('/'),
            'view'  => ViewContactRequest::route('/{record}'),
            'edit'  => EditContactRequest::route('/{record}/edit'),
        ];
    }
}
