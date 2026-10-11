<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->target()->update([
            'is_active' => false,
            'is_archived' => true,
            'in_stock' => false,
            'stock_qty' => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $this->target()->update([
            'is_active' => true,
            'is_archived' => false,
            'updated_at' => now(),
        ]);
    }

    private function target()
    {
        return DB::table('products')->where(function ($query): void {
            $query->where('sku', 'KOTLOV-006527')
                ->orWhere(function ($byName): void {
                    $byName->where('name', 'like', '%Blist%')
                        ->where('name', 'like', '%Труб%')
                        ->where('name', 'like', '%0,5%')
                        ->where('name', 'like', '%Серби%');
                });
        });
    }
};
