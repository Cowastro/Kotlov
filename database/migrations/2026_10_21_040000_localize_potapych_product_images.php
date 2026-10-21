<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $images = [
            'pech-otopitelnaya-tsar-pechi-potapyich' => 'img/products/tsar-pechi/potapych-3mm-v2.webp',
            'pech-otopitelnaya-tsar-pechi-potapyich-s-konforkoy-3-mm' => 'img/products/tsar-pechi/potapych-hob-3mm-v2.webp',
            'pech-otopitelnaya-tsar-pechi-potapyich-s-pelletnoy-gorelkoy' => 'img/products/tsar-pechi/potapych-pellet-v2.webp',
            'pech-otopitelnaya-tsar-pechi-potapyich-s-konforkoy-i-pelletnoy-gorelkoy' => 'img/products/tsar-pechi/potapych-hob-pellet-v2.webp',
        ];

        foreach ($images as $slug => $path) {
            DB::table('products')
                ->where('slug', $slug)
                ->update([
                    'images' => json_encode([$path], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        Cache::forget('home:data');
        Cache::forget('home:data:v3');
    }

    public function down(): void
    {
        // Local product media is intentionally durable.
    }
};
