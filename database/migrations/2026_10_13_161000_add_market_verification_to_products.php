<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('market_price_verified_at')->nullable()->after('price_old');
            $table->text('market_price_source_url')->nullable()->after('market_price_verified_at');
            $table->string('supplier_candidate_name')->nullable()->after('market_price_source_url');
            $table->text('supplier_candidate_url')->nullable()->after('supplier_candidate_name');
        });

        $sources = [
            'PS-005.684' => 'https://progreem.by/catalog/otoplenie/avtomatika-sistemy-otopleniya/stabilizatory-i-ibp/ibp-garnt-01/',
            'PS-007.061' => 'https://progreem.by/catalog/otoplenie/avtomatika-sistemy-otopleniya/stabilizatory-i-ibp/ibp-garnt-02/',
            'PS-007.027' => 'https://progreem.by/catalog/otoplenie/avtomatika-sistemy-otopleniya/stabilizatory-i-ibp/ibp-pro-01/',
        ];

        foreach ($sources as $sku => $sourceUrl) {
            $this->unlinkedProduct($sku)->update([
                'market_price_verified_at' => now(),
                'market_price_source_url' => $sourceUrl,
                'supplier_candidate_name' => 'Официальный дистрибьютор ЭТК Энергия',
                'supplier_candidate_url' => 'https://energy-ltd.ru/catalog/ibp',
                'updated_at' => now(),
            ]);
        }

        $this->unlinkedProduct('PS-005.700')->update([
            'market_price_source_url' => 'https://energy-ltd.ru/products/interaktivnyj-ibp-energiya-pn-750',
            'supplier_candidate_name' => 'Официальный дистрибьютор ЭТК Энергия',
            'supplier_candidate_url' => 'https://energy-ltd.ru/catalog/ibp',
            'updated_at' => now(),
        ]);
    }

    private function unlinkedProduct(string $sku)
    {
        return DB::table('products')
            ->where('sku', $sku)
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('supplier_products')
                ->whereColumn('supplier_products.product_id', 'products.id'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('integration_products')
                ->join('integration_sources', 'integration_sources.id', '=', 'integration_products.integration_source_id')
                ->whereColumn('integration_products.product_id', 'products.id')
                ->where('integration_products.match_status', 'matched')
                ->where('integration_sources.is_active', true));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'market_price_verified_at',
                'market_price_source_url',
                'supplier_candidate_name',
                'supplier_candidate_url',
            ]);
        });
    }
};
