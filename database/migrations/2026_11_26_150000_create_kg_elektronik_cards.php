<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * KG Elektronik (Poland, through ООО «СанБизнесГруп»): the brand and 66 cards from database/data/kg_elektronik_2027.json,
 * preorder. Cards that already exist are kept (catalog:create-kg-elektronik --refresh rewrites their content).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! DB::table('suppliers')->where('code', 'sanbusinessgroup')->exists()) {
            return;
        }
        Artisan::call('catalog:create-kg-elektronik', ['--apply' => true]);
    }

    public function down(): void
    {
        // The cards stay: they may already be in orders.
    }
};
