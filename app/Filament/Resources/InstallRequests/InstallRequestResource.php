<?php

namespace App\Filament\Resources\InstallRequests;

use App\Filament\Resources\InstallRequests\Pages\CreateInstallRequest;
use App\Filament\Resources\InstallRequests\Pages\EditInstallRequest;
use App\Filament\Resources\InstallRequests\Pages\ListInstallRequests;
use App\Filament\Resources\InstallRequests\Pages\ViewInstallRequest;
use App\Filament\Resources\InstallRequests\Schemas\InstallRequestForm;
use App\Filament\Resources\InstallRequests\Schemas\InstallRequestInfolist;
use App\Filament\Resources\InstallRequests\Tables\InstallRequestsTable;
use App\Models\InstallRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallRequestResource extends Resource
{
    protected static ?string $model = InstallRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InstallRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallRequests::route('/'),
            'create' => CreateInstallRequest::route('/create'),
            'view' => ViewInstallRequest::route('/{record}'),
            'edit' => EditInstallRequest::route('/{record}/edit'),
        ];
    }
}
