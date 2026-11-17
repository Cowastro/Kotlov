<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table): void {
            $table->string('supplier_catalog_code')->nullable()->index();
        });
        $supplier = DB::table('suppliers')->where('code', 'td_komplekt')->value('id');
        if ($supplier) {
            DB::table('supplier_products')->where('supplier_id', $supplier)->orderBy('id')
                ->select(['id', 'raw'])->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $raw = json_decode($row->raw ?? '{}', true) ?: [];
                        $code = mb_strtoupper(preg_replace('/\s+/u', '', trim($raw['okdp'] ?? '')));
                        DB::table('supplier_products')->where('id', $row->id)
                            ->update(['supplier_catalog_code' => $code ?: null]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('supplier_products', fn (Blueprint $table) => $table->dropColumn('supplier_catalog_code'));
    }
};
