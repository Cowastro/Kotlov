<?php

use App\Models\Supplier;
use App\Services\Suppliers\SakovichPelletKits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $supplier = Supplier::where('code', 'santehsklad')->first();
        if ($supplier) {
            DB::transaction(fn () => app(SakovichPelletKits::class)->refresh($supplier));
        }
    }

    public function down(): void {}
};
