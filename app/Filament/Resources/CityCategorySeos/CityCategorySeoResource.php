<?php

namespace App\Filament\Resources\CityCategorySeos;

use App\Filament\Resources\CityCategorySeos\Pages\CreateCityCategorySeo;
use App\Filament\Resources\CityCategorySeos\Pages\EditCityCategorySeo;
use App\Filament\Resources\CityCategorySeos\Pages\ListCityCategorySeos;
use App\Filament\Resources\CityCategorySeos\Schemas\CityCategorySeoForm;
use App\Filament\Resources\CityCategorySeos\Tables\CityCategorySeosTable;
use App\Models\CityCategorySeo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CityCategorySeoResource extends Resource
{
    protected static ?string $model = CityCategorySeo::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;
    protected static ?string $navigationLabel = 'SEO городов';
    protected static ?string $modelLabel = 'SEO категории в городе';
    protected static ?string $pluralModelLabel = 'SEO городов';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string { return 'Каталог'; }
    public static function form(Schema $schema): Schema { return CityCategorySeoForm::configure($schema); }
    public static function table(Table $table): Table { return CityCategorySeosTable::configure($table); }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index' => ListCityCategorySeos::route('/'),
            'create' => CreateCityCategorySeo::route('/create'),
            'edit' => EditCityCategorySeo::route('/{record}/edit'),
        ];
    }
}
