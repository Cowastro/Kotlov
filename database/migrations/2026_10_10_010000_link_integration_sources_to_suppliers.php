<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $source = DB::table('integration_sources')->where('code', 'onec')->first();
        if (! $source) {
            return;
        }

        $supplier = DB::table('suppliers')
            ->where('code', 'sanbusinessgroup')
            ->orWhere('name', 'ООО «СанБизнесГруп»')
            ->first();

        $supplierId = $supplier?->id;
        if (! $supplierId) {
            $supplierId = DB::table('suppliers')->insertGetId([
                'code' => 'sanbusinessgroup',
                'name' => 'ООО «СанБизнесГруп»',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'is_active' => true,
                'notes' => 'Основной поставщик собственного каталога 1С.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('integration_sources')->where('id', $source->id)->update([
            'supplier_id' => $supplierId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
