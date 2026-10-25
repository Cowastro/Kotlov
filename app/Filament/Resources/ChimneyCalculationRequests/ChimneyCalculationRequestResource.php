<?php

namespace App\Filament\Resources\ChimneyCalculationRequests;

use App\Filament\Resources\ChimneyCalculationRequests\Pages\ListChimneyCalculationRequests;
use App\Filament\Resources\ChimneyCalculationRequests\Pages\ViewChimneyCalculationRequest;
use App\Filament\Resources\ChimneyCalculationRequests\Schemas\ChimneyCalculationRequestInfolist;
use App\Filament\Resources\ChimneyCalculationRequests\Tables\ChimneyCalculationRequestsTable;
use App\Models\ChimneyCalculationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChimneyCalculationRequestResource extends Resource
{
    protected static ?string $model = ChimneyCalculationRequest::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Расчёты дымоходов';

    protected static ?string $modelLabel = 'Расчёт дымохода';

    protected static ?string $pluralModelLabel = 'Расчёты дымоходов';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChimneyCalculationRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChimneyCalculationRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChimneyCalculationRequests::route('/'),
            'view' => ViewChimneyCalculationRequest::route('/{record}'),
        ];
    }
}
