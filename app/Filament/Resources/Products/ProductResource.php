<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\AttributeValuesRelationManager;
use App\Filament\Resources\Products\RelationManagers\IntegrationProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\PurchasePriceSourcesRelationManager;
use App\Filament\Resources\Products\RelationManagers\SupplierProductsRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Товары';

    protected static ?string $modelLabel = 'Товар';

    protected static ?string $pluralModelLabel = 'Товары';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    // Eager load связей чтобы избежать N+1 в таблице
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('marketResearchReports')
            ->withCount('supplierMailThreads')
            ->with([
                'category:id,name,slug',
                'brand:id,name',
                // products.supplier_id is the legacy supplier user account,
                // not a row from the suppliers table.
                'supplier:id,name',
                'supplierProducts:id,product_id,supplier_id,supplier_article,price_byn,in_stock,stock_quantity,stock_status,last_stock_synced_at,last_synced_at,updated_at',
                'supplierProducts.supplier:id,crm_counterparty_id,name,contact,is_active',
                'supplierProducts.supplier.crmCounterparty.contactPoints',
                'integrationProducts:id,product_id,integration_source_id,external_sku,price,price_currency,price_currency_rate,price_tax_mode,price_vat_rate,price_byn,stock_quantity,match_status,stock_confirmed_at',
                'integrationProducts.source:id,supplier_id,name,driver,price_currency,price_currency_rate,settings,is_active',
                'integrationProducts.source.supplier:id,crm_counterparty_id,name,contact,is_active',
                'integrationProducts.source.supplier.crmCounterparty.contactPoints',
                'marketPriceObservations.source',
                'latestMarketResearchReport',
                'latestSupplierMailThread.supplier:id,name',
                'latestSupplierMailThread.latestMessage',
                'supplierMailThreads:id,product_id,supplier_id,status,last_message_at,participant_name,participant_email',
                'supplierMailThreads.supplier:id,name',
                'purchasePriceSources',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PurchasePriceSourcesRelationManager::class,
            IntegrationProductsRelationManager::class,
            SupplierProductsRelationManager::class,
            AttributeValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
