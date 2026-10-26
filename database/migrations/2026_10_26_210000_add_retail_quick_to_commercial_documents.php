<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->boolean('retail_quick')->default(false)->after('allow_unconfirmed_purchase_price');
        });

        DB::table('commercial_documents')
            ->whereNull('client_company')
            ->whereNull('client_legal_name')
            ->whereNull('client_unp')
            ->where('notes', 'like', 'Создано из заказа %')
            ->update([
                'retail_quick' => true,
                'allow_unconfirmed_purchase_price' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropColumn('retail_quick');
        });
    }
};
