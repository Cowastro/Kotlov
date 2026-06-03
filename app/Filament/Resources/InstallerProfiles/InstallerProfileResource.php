<?php

namespace App\Filament\Resources\InstallerProfiles;

use App\Filament\Resources\InstallerProfiles\Pages\CreateInstallerProfile;
use App\Filament\Resources\InstallerProfiles\Pages\EditInstallerProfile;
use App\Filament\Resources\InstallerProfiles\Pages\ListInstallerProfiles;
use App\Filament\Resources\InstallerProfiles\Pages\ViewInstallerProfile;
use App\Filament\Resources\InstallerProfiles\Schemas\InstallerProfileForm;
use App\Filament\Resources\InstallerProfiles\Schemas\InstallerProfileInfolist;
use App\Filament\Resources\InstallerProfiles\RelationManagers\InstallerReviewsRelationManager;
use App\Filament\Resources\InstallerProfiles\RelationManagers\WorksRelationManager;
use App\Filament\Resources\InstallerProfiles\Tables\InstallerProfilesTable;
use App\Models\InstallerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallerProfileResource extends Resource
{
    protected static ?string $model = InstallerProfile::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;
    protected static ?string $navigationLabel = 'Монтажники';
    protected static ?string $modelLabel = 'Монтажник';
    protected static ?string $pluralModelLabel = 'Монтажники';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string { return 'Продажи'; }
    public static function form(Schema $schema): Schema { return InstallerProfileForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return InstallerProfileInfolist::configure($schema); }
    public static function table(Table $table): Table { return InstallerProfilesTable::configure($table); }
    public static function getRelations(): array
    {
        return [
            WorksRelationManager::class,
            InstallerReviewsRelationManager::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index'  => ListInstallerProfiles::route('/'),
            'create' => CreateInstallerProfile::route('/create'),
            'view'   => ViewInstallerProfile::route('/{record}'),
            'edit'   => EditInstallerProfile::route('/{record}/edit'),
        ];
    }
}
