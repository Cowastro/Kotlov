<?php

namespace App\Filament\Resources\SupportConversations;

use App\Filament\Resources\SupportConversations\Pages\ListSupportConversations;
use App\Filament\Resources\SupportConversations\Pages\ViewSupportConversation;
use App\Models\SupportConversation;
use Filament\Resources\Resource;

class SupportConversationResource extends Resource
{
    protected static ?string $model = SupportConversation::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return [
            'index' => ListSupportConversations::route('/'),
            'view' => ViewSupportConversation::route('/{record}'),
        ];
    }
}
