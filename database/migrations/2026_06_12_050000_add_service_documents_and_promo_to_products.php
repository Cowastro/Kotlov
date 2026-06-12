<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'service_info')) {
                $table->json('service_info')->nullable()->after('specs');
            }

            if (! Schema::hasColumn('products', 'documents')) {
                $table->json('documents')->nullable()->after('service_info');
            }

            if (! Schema::hasColumn('products', 'promo_flags')) {
                $table->json('promo_flags')->nullable()->after('documents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['promo_flags', 'documents', 'service_info'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
