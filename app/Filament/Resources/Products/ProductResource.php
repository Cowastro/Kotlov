<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\AttributeValuesRelationManager;
use App\Filament\Resources\Products\RelationManagers\SupplierProductsRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;
    protected static ?string $navigationLabel = 'Товары';
    protected static ?string $modelLabel = 'Товар';
    protected static ?string $pluralModelLabel = 'Товары';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string { return 'Каталог'; }

    // Eager load связей чтобы избежать N+1 в таблице
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'category:id,name,slug',
                'brand:id,name',
                'supplier:id,name',
                'supplierProducts:id,product_id,supplier_id,supplier_article,price_byn,stock_quantity,stock_status',
            ]);
    }
    public static function form(Schema $schema): Schema { return ProductForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return ProductInfolist::configure($schema); }
    public static function table(Table $table): Table { return ProductsTable::configure($table); }
    public static function getRelations(): array
    {
        return [
            SupplierProductsRelationManager::class,
            AttributeValuesRelationManager::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view'   => ViewProduct::route('/{record}'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
