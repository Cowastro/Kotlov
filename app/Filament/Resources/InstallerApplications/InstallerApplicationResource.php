<?php

namespace App\Filament\Resources\InstallerApplications;

use App\Filament\Resources\InstallerApplications\Pages\ListInstallerApplications;
use App\Filament\Resources\InstallerApplications\Pages\ViewInstallerApplication;
use App\Filament\Resources\InstallerApplications\Pages\EditInstallerApplication;
use App\Filament\Resources\InstallerApplications\Schemas\InstallerApplicationInfolist;
use App\Filament\Resources\InstallerApplications\Schemas\InstallerApplicationForm;
use App\Filament\Resources\InstallerApplications\Tables\InstallerApplicationsTable;
use App\Models\InstallerApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstallerApplicationResource extends Resource
{
    protected static ?string $model = InstallerApplication::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static ?string $navigationLabel = 'Заявки монтажников';
    protected static ?string $modelLabel = 'Заявка монтажника';
    protected static ?string $pluralModelLabel = 'Заявки монтажников';
    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string { return 'Партнёры'; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema { return InstallerApplicationForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return InstallerApplicationInfolist::configure($schema); }
    public static function table(Table $table): Table { return InstallerApplicationsTable::configure($table); }
    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallerApplications::route('/'),
            'view'  => ViewInstallerApplication::route('/{record}'),
            'edit'  => EditInstallerApplication::route('/{record}/edit'),
        ];
    }
}
