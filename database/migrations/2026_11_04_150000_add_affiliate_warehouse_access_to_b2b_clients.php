<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->boolean('allow_all_integration_sources')->default(false)->after('allowed_integration_source_ids');
            $table->boolean('allow_all_integration_warehouses')->default(false)->after('allowed_integration_warehouse_ids');
            $table->boolean('allow_all_partner_suppliers')->default(false)->after('allowed_supplier_ids');
        });

    }

    public function down(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropColumn([
                'allow_all_integration_sources',
                'allow_all_integration_warehouses',
                'allow_all_partner_suppliers',
            ]);
        });
    }
};
