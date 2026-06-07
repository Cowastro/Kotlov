<?php

namespace App\Filament\Resources\SupplierApplications;

use App\Filament\Resources\SupplierApplications\Pages\ListSupplierApplications;
use App\Filament\Resources\SupplierApplications\Pages\ViewSupplierApplication;
use App\Filament\Resources\SupplierApplications\Pages\EditSupplierApplication;
use App\Filament\Resources\SupplierApplications\Schemas\SupplierApplicationInfolist;
use App\Filament\Resources\SupplierApplications\Schemas\SupplierApplicationForm;
use App\Filament\Resources\SupplierApplications\Tables\SupplierApplicationsTable;
use App\Models\SupplierApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupplierApplicationResource extends Resource
{
    protected static ?string $model = SupplierApplication::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;
    protected static ?string $navigationLabel = 'Заявки поставщиков';
    protected static ?string $modelLabel = 'Заявка поставщика';
    protected static ?string $pluralModelLabel = 'Заявки поставщиков';
    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string { return 'Партнёры'; }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'new')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema { return SupplierApplicationForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return SupplierApplicationInfolist::configure($schema); }
    public static function table(Table $table): Table { return SupplierApplicationsTable::configure($table); }
    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierApplications::route('/'),
            'view'  => ViewSupplierApplication::route('/{record}'),
            'edit'  => EditSupplierApplication::route('/{record}/edit'),
        ];
    }
}
