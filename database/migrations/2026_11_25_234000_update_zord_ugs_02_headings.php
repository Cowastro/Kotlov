<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('sku', 'PS-011.094')->update([
            'h1' => 'ЗОРД УГС-02 — сигнализатор угарного и горючих газов',
            'meta_title' => 'ЗОРД УГС-02 — купить сигнализатор угарного и горючих газов | KOTLOV',
        ]);
    }
};
