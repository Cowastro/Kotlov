<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_product_category_moves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('integration_product_id')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('from_category_id')->nullable();
            $table->unsignedBigInteger('to_category_id');
            $table->timestamps();
        });

        DB::table('integration_products as ip')
            ->join('products as product', 'product.id', '=', 'ip.product_id')
            ->whereNotNull('ip.product_id')
            ->whereNotNull('ip.target_category_id')
            ->whereIn('ip.product_id', DB::table('integration_products')
                ->whereNotNull('product_id')
                ->whereNotNull('target_category_id')
                ->select('product_id')
                ->groupBy('product_id')
                ->havingRaw('COUNT(DISTINCT target_category_id) = 1'))
            ->where(function ($query): void {
                $query->whereNull('product.category_id')
                    ->orWhereColumn('product.category_id', '!=', 'ip.target_category_id');
            })
            ->select([
                'ip.id',
                'ip.product_id',
                'ip.target_category_id',
                'product.category_id as from_category_id',
            ])
            ->orderBy('ip.id')
            ->chunkById(500, function ($items): void {
                foreach ($items as $item) {
                    DB::table('integration_product_category_moves')->updateOrInsert(
                        ['integration_product_id' => $item->id],
                        [
                            'product_id' => $item->product_id,
                            'from_category_id' => $item->from_category_id,
                            'to_category_id' => $item->target_category_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    DB::table('products')
                        ->where('id', $item->product_id)
                        ->where(function ($query) use ($item): void {
                            $query->whereNull('category_id')
                                ->orWhere('category_id', '!=', $item->target_category_id);
                        })
                        ->update([
                            'category_id' => $item->target_category_id,
                            'updated_at' => now(),
                        ]);
                }
            }, 'ip.id', 'id');
    }

    public function down(): void
    {
        DB::table('integration_product_category_moves')
            ->orderBy('id')
            ->chunkById(500, function ($moves): void {
                foreach ($moves as $move) {
                    DB::table('products')
                        ->where('id', $move->product_id)
                        ->where('category_id', $move->to_category_id)
                        ->update([
                            'category_id' => $move->from_category_id,
                            'updated_at' => now(),
                        ]);
                }
            });

        Schema::dropIfExists('integration_product_category_moves');
    }
};
