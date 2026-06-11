<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_products', 'currency_rate')) {
            Schema::table('supplier_products', function (Blueprint $table) {
                $table->decimal('currency_rate', 10, 4)->default(1)->after('currency');
            });
        }

        if (! Schema::hasColumn('supplier_products', 'price_byn')) {
            Schema::table('supplier_products', function (Blueprint $table) {
                $table->decimal('price_byn', 12, 2)->nullable()->after('currency_rate');
            });
        }

        if (! Schema::hasColumn('supplier_price_items', 'currency')) {
            Schema::table('supplier_price_items', function (Blueprint $table) {
                $table->string('currency', 3)->default('BYN')->after('price');
            });
        }

        if (! Schema::hasColumn('supplier_price_items', 'currency_rate')) {
            Schema::table('supplier_price_items', function (Blueprint $table) {
                $table->decimal('currency_rate', 10, 4)->default(1)->after('currency');
            });
        }

        // Backfill: все существующие записи синхронизаций в BYN с курсом 1.
        DB::statement('UPDATE supplier_products SET price_byn = ROUND(price * currency_rate, 2) WHERE price IS NOT NULL AND price_byn IS NULL');

        // Backfill: зафиксировать валюту/курс поставщика на строках прайсов.
        DB::statement('UPDATE supplier_price_items spi JOIN suppliers s ON s.id = spi.supplier_id SET spi.currency = s.currency, spi.currency_rate = s.currency_rate');
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn(['currency_rate', 'price_byn']);
        });

        Schema::table('supplier_price_items', function (Blueprint $table) {
            $table->dropColumn(['currency', 'currency_rate']);
        });
    }
};
